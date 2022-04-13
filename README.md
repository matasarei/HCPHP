# HCPHP
HCPHP is a PHP framework with basic functionality to build simple and fast web applications.

## Why
Started as a student project in 2014. The goal is to implement small but complete MVC framework.

## Setup
```shell
docker-compose up -d
docker-compose exec fpm sh

./run user:create world@email.xyz World p@ssw0rd
./run relations:add "test"
```
http://localhost:8080/

## Core
The `core` directory includes some core services such as:
* Application interface - base interface, responsible for routing and request processing.
* Simple DI container
* Simple caching
* Simple event processing
* Database interface
* L18n interface
* Templates

### Application
The core interface responsible for starting the web app, routing and dependency-injection.

### Events
Events interface to build event-based logic.

### Database
MySQL database interface to build and execute SQL queries.

### Object model
Interfaces to build repositories for database routines (like search, save and remove objects)
and mappers for data transformations (from raw data to objects and vise-versa).

## Configurations
All configuration files should be stored in the `config` directory.
Out-of-the-box supported format for configuration files is `json`.

To access configurations use the `core\Config` class:
```php
use core\Config;

// load the `config/name.json`.
$config = new Config(
    'name', 
    [
        'value1' => 'default1', 
        'value2' => 'default2'
    ]
);

x($config->get('value1'));
```

## Controllers
```php
// controllers/foo.php
class FooController extends Controller
{
    function actionDefault()
    {
        // someapp.xyz/foo
    }

    function actionBar(string $argument1, string $argument2 = null)
    {
        // someapp.xyz/foo/bar/argument1[/argument2]
    }
}
```

## Events
To handle events use handler classes:
```php
// events/FooHandler.php
class FooHandler extends Handler
{
    protected function handle($data)
    {
        // do something
    }
}
```

To fire event:
```php
Events::triggerEvent('Foo', ['parameter' => 'qwerty']);
```

## Custom classes
All custom classes should be added to the `lib` directory.

For example: you have a `Foo` bundle that includes some `Foo` entity, 
a repository and other related classes \ services, then the structure should look like this:
```
lib/
  Foo/
    FooService.php
    Entity/
      Foo.php
    Repository/
      FooRepository.php
    Mapper/ (?)
      FooMapper.php
    Validator/
      FooValidator.php
```

In this example the `FooService.php` is a class with some business logic, like:
```php
class FooService
{
   function doSomethingWithFoo(Foo $foo)
   {
      // do something
   }
}
```
An entity itself should not include any business logic like validation etc.

## Templates
```php
$template = new Template('foo'); // templates/foo.php
$template->set('bar', 'mixed value');
$template->set('array', ['name' => 'value']);
$template->set('object', (object)['name' => 'value']);
echo $template->make(); // build from template.
```
```php
<!-- templates/foo.php -->
<p>{{$bar}} or <?php echo $bar ?></p>
<p>{{$array['name']]}}</p>
<p>{{$object->name}}</p>
```

Views are also part of templates subsystem, but views have special role: display text or html output via controllers:
```php
use core\View;

class FooController extends Controller
{
    function actionDefault()
    {
        // views/foo/default.php
        return (new View())
            ->set('name', 'value')
            ->set('foo', 'bar')
        ;
    }
}
```

## Multilingual
Use the `Language` interface to build a multilingual app:
```php
// Hello World!
Language::getInstance('en')->getString('hello', ['World!']);
```

`lang/en.json`:
```json
{
  "hello": "Hello %s"
}
```

On templates use a shortcut:
```html
<h1>{{lang|'hello'|['World!']}}</h1>
```

## CLI
CLI interface represents by the command classes.

```php
// commands/foo_bar.php
// run.php foo:bar "argument"
class FooBarCommand extends Command
{
    public function run(): int
    {
        //do something.
        return 0;
    }

    protected function parseArguments(array $args)
    {
        if (!isset($args[0])) {
            throw new InvalidArgumentException('Missing required arguments, see help.');
        }

        $this->setArgument('argument', $args[0]);
    }

    protected function getHelp(): string
    {
        return 'Help message';
    }
}
```

## Debug and logging
```php
use core\Debug;

Debug::dump('mixed values'); // or simply use x()
```
