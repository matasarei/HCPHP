<?php

namespace Tests\Unit\DynamicDB;

use DynamicDB\Field\Boolean;
use DynamicDB\Field\DateTime;
use DynamicDB\Field\Enum;
use DynamicDB\Field\File;
use DynamicDB\Field\Integer;
use DynamicDB\Field\JSON;
use DynamicDB\Field\LongText;
use DynamicDB\Field\MediumText;
use DynamicDB\Field\Real;
use DynamicDB\Field\Relation;
use DynamicDB\Field\Text;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingDatabase;

/**
 * These classes turn a field definition into the DDL that creates or alters a column, which
 * is the only thing standing between config/dynamicdb.json and the live schema.
 */
class FieldTypesTest extends TestCase
{
    /**
     * @var RecordingDatabase
     */
    private $database;

    protected function setUp(): void
    {
        $this->database = new RecordingDatabase();
    }

    private function sql(): string
    {
        return $this->database->getLastStatementNormalised();
    }

    // --- the shared base -------------------------------------------------------------------

    public function testNameIsReadableAndChangeable(): void
    {
        $field = new Integer($this->database, 'records', 'count');

        self::assertSame('count', $field->getName());
        self::assertSame('renamed', $field->setName('renamed')->getName());
    }

    public function testRemoveDropsTheColumn(): void
    {
        (new Integer($this->database, 'records', 'count'))->remove();

        self::assertSame('ALTER TABLE `records` DROP COLUMN `count`;', $this->sql());
    }

    public function testAfterPlacesTheColumn(): void
    {
        (new Integer($this->database, 'records', 'count'))->setAfter('id')->create();

        self::assertStringContainsString('AFTER `id`', $this->sql());
    }

    public function testIsExistQueriesTheSchema(): void
    {
        // The read goes to the real sqlite connection, which has no SHOW COLUMNS; what
        // matters here is that the field asks rather than assumes.
        $field = new Integer($this->database, 'records', 'count');

        $this->expectException(\PDOException::class);

        $field->isExist();
    }

    // --- Integer ---------------------------------------------------------------------------

    public function testIntegerCreatesAnIntColumn(): void
    {
        (new Integer($this->database, 'records', 'count'))->create();

        self::assertStringContainsString('ADD COLUMN `count` INT(11)', $this->sql());
        self::assertStringContainsString("NOT NULL DEFAULT '0'", $this->sql());
    }

    /**
     * @dataProvider integerLengthProvider
     */
    public function testIntegerWidthPicksTheType(int $length, string $expected): void
    {
        $field = (new Integer($this->database, 'records', 'count'));
        $field->setLength($length);
        $field->create();

        self::assertStringContainsString($expected, $this->sql());
        self::assertSame($length, $field->getLength());
    }

    public function integerLengthProvider(): array
    {
        return [
            'over 10 is big' => [11, 'BIGINT(11)'],
            'over 7 is plain' => [8, 'INT(8)'],
            'over 3 is medium' => [4, 'MEDIUMINT(4)'],
            'small is tiny' => [2, 'TINYINT(2)'],
            'one is tiny' => [1, 'TINYINT(1)'],
        ];
    }

    public function testIntegerUnsignedIsRendered(): void
    {
        $field = (new Integer($this->database, 'records', 'count'))->setUnsigned(true);
        $field->create();

        self::assertTrue($field->isUnsigned());
        self::assertStringContainsString('UNSIGNED', $this->sql());
    }

    /**
     * A single-digit column cannot hold a sign, so asking for one is dropped.
     */
    public function testLengthOfOneClearsUnsigned(): void
    {
        $field = (new Integer($this->database, 'records', 'flag'))->setUnsigned(true);
        $field->setLength(1);

        self::assertFalse($field->isUnsigned());
    }

    public function testIntegerDefaultIsCoercedToAnInt(): void
    {
        $field = new Integer($this->database, 'records', 'count');
        $field->setDefault('42');

        self::assertSame(42, $field->getDefault());
    }

    public function testIntegerAddValueAccumulates(): void
    {
        $field = new Integer($this->database, 'records', 'count');
        $field->setDefault(5)->addValue(3);

        self::assertSame(8, $field->getDefault());
    }

    public function testIntegerUpdateRenamesTheColumn(): void
    {
        (new Integer($this->database, 'records', 'count'))->update('total');

        self::assertStringContainsString('CHANGE COLUMN `count` `total`', $this->sql());
    }

    public function testIntegerUpdateKeepsTheNameWhenNoRenameIsGiven(): void
    {
        (new Integer($this->database, 'records', 'count'))->update();

        self::assertStringContainsString('CHANGE COLUMN `count` `count`', $this->sql());
    }

    // --- Text ------------------------------------------------------------------------------

    public function testTextCreatesAVarchar(): void
    {
        (new Text($this->database, 'records', 'title'))->create();

        self::assertStringContainsString('ADD COLUMN `title` VARCHAR(255)', $this->sql());
    }

    public function testTextBecomesTextBeyondTheVarcharLimit(): void
    {
        $field = new Text($this->database, 'records', 'body');
        $field->setLength(Text::LENGTH_MAX + 1);
        $field->create();

        self::assertStringContainsString('TEXT', $this->sql());
        self::assertStringNotContainsString('VARCHAR', $this->sql());
    }

    public function testTextDefaultIsQuotedAndEscaped(): void
    {
        $field = new Text($this->database, 'records', 'title');
        $field->setDefault("O'Brien");
        $field->create();

        self::assertStringContainsString("DEFAULT 'O\\'Brien'", $this->sql());
    }

    public function testTextWithNoDefaultIsNull(): void
    {
        (new Text($this->database, 'records', 'title'))->create();

        self::assertStringContainsString('DEFAULT NULL', $this->sql());
    }

    public function testTextAddValueConcatenates(): void
    {
        $field = new Text($this->database, 'records', 'tags');
        $field->addValue('a')->addValue('b');

        self::assertSame('a;b', $field->getDefault());
    }

    public function testTextUpdateRenames(): void
    {
        (new Text($this->database, 'records', 'title'))->update('name');

        self::assertStringContainsString('CHANGE COLUMN `title` `name`', $this->sql());
    }

    // --- MediumText / LongText / JSON --------------------------------------------------------

    public function testMediumTextUsesItsOwnColumnType(): void
    {
        (new MediumText($this->database, 'records', 'body'))->create();

        self::assertStringContainsString('MEDIUMTEXT', $this->sql());
    }

    public function testLongTextUsesItsOwnColumnType(): void
    {
        (new LongText($this->database, 'records', 'body'))->create();

        self::assertStringContainsString('LONGTEXT', $this->sql());
    }

    public function testJsonIsStoredAsMediumText(): void
    {
        (new JSON($this->database, 'records', 'meta'))->create();

        self::assertStringContainsString('MEDIUMTEXT', $this->sql());
    }

    /**
     * These types have one width, so asking to change it is a mistake worth reporting rather
     * than silently ignoring.
     */
    public function testMediumTextRefusesALengthChange(): void
    {
        $this->expectException(LogicException::class);

        (new MediumText($this->database, 'records', 'body'))->setLength(10);
    }

    // --- File --------------------------------------------------------------------------------

    public function testFileIsAVarcharOfTheFilenameLength(): void
    {
        (new File($this->database, 'records', 'doc'))->create();

        self::assertStringContainsString('VARCHAR(255)', $this->sql());
    }

    public function testFileAcceptsAShorterLength(): void
    {
        $field = new File($this->database, 'records', 'doc');
        $field->setLength(64);
        $field->create();

        self::assertStringContainsString('VARCHAR(64)', $this->sql());
    }

    /**
     * The guard threw http\Exception\InvalidArgumentException, from the pecl_http extension,
     * which is not installed: asking for an over-long filename column was a fatal error about
     * a missing class rather than the intended complaint.
     */
    public function testFileRefusesAnOverLongNameWithARealException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max supported filename length is 255 bytes');

        (new File($this->database, 'records', 'doc'))->setLength(256);
    }

    // --- Real ----------------------------------------------------------------------------------

    public function testRealCreatesADecimal(): void
    {
        $field = new Real($this->database, 'records', 'price');
        $field->setLength(10.2);
        $field->create();

        self::assertStringContainsString('DECIMAL(10,2)', $this->sql());
    }

    public function testRealDefaultIsCoercedToAFloat(): void
    {
        $field = new Real($this->database, 'records', 'price');
        $field->setDefault('1.5');

        self::assertSame(1.5, $field->getDefault());
    }

    public function testRealAddValueAccumulates(): void
    {
        $field = new Real($this->database, 'records', 'price');
        $field->setDefault(1.5)->addValue(2.0);

        self::assertSame(3.5, $field->getDefault());
    }

    /**
     * The clamp assigned to the integer part when it was the fractional part that was too
     * long, turning DECIMAL(12,40) into DECIMAL(30,40) -- still invalid, and now wrong about
     * the whole number too.
     */
    public function testRealClampsTheFractionalPartItComplainsAbout(): void
    {
        $field = new Real($this->database, 'records', 'price');

        @$field->setLength(12.4);
        $field->create();

        self::assertStringContainsString('DECIMAL(12,', $this->sql(), 'the integer part is untouched');
    }

    public function testRealUpdateRenames(): void
    {
        $field = new Real($this->database, 'records', 'price');
        $field->setLength(8.2);
        $field->update('cost');

        self::assertStringContainsString('CHANGE COLUMN `price` `cost`', $this->sql());
    }

    // --- Enum ------------------------------------------------------------------------------------

    public function testEnumListsItsValues(): void
    {
        $field = new Enum($this->database, 'records', 'state');
        $field->addValue('draft')->addValue('live');
        $field->create();

        self::assertStringContainsString("ENUM('draft','live')", $this->sql());
    }

    public function testEnumIgnoresADuplicateValue(): void
    {
        $field = new Enum($this->database, 'records', 'state');
        $field->addValue('draft')->addValue('draft');
        $field->create();

        self::assertStringContainsString("ENUM('draft')", $this->sql());
    }

    public function testEnumDefaultsToItsFirstValue(): void
    {
        $field = new Enum($this->database, 'records', 'state');
        $field->addValue('draft')->addValue('live');
        $field->create();

        self::assertStringContainsString("DEFAULT 'draft'", $this->sql());
    }

    public function testEnumKeepsAnExplicitDefault(): void
    {
        $field = new Enum($this->database, 'records', 'state');
        $field->addValue('draft')->addValue('live')->setDefault('live');
        $field->create();

        self::assertStringContainsString("DEFAULT 'live'", $this->sql());
    }

    public function testEnumUpdateRenames(): void
    {
        $field = new Enum($this->database, 'records', 'state');
        $field->addValue('draft');
        $field->update('status');

        self::assertStringContainsString('CHANGE COLUMN `state` `status`', $this->sql());
    }

    // --- Boolean ------------------------------------------------------------------------------------

    public function testBooleanIsATinyIntFlag(): void
    {
        (new Boolean($this->database, 'records', 'active'))->create();

        self::assertStringContainsString('TINYINT(1) UNSIGNED', $this->sql());
        self::assertStringContainsString("DEFAULT '0'", $this->sql());
    }

    public function testBooleanDefaultIsCoerced(): void
    {
        $field = new Boolean($this->database, 'records', 'active');
        $field->setDefault('yes');
        $field->create();

        self::assertStringContainsString("DEFAULT '1'", $this->sql());
    }

    public function testBooleanUpdateRenames(): void
    {
        (new Boolean($this->database, 'records', 'active'))->update('enabled');

        self::assertStringContainsString('CHANGE COLUMN `active` `enabled`', $this->sql());
    }

    // --- DateTime and Relation --------------------------------------------------------------------

    public function testDateTimeIsAnUnsignedTimestamp(): void
    {
        (new DateTime($this->database, 'records', 'created'))->create();

        self::assertStringContainsString('INT(10)', $this->sql());
        self::assertStringContainsString('UNSIGNED', $this->sql());
    }

    public function testRelationIsAnIntegerForeignKey(): void
    {
        $field = new Relation($this->database, 'records', 'owner');
        $field->create();

        self::assertSame(10, $field->getLength());
        self::assertStringContainsString('INT(10)', $this->sql());
    }

    public function testRelationLengthStillPicksAType(): void
    {
        $field = new Relation($this->database, 'records', 'owner');
        $field->setLength(11);
        $field->create();

        self::assertStringContainsString('BIGINT(11)', $this->sql());
    }
}
