<?php
/**
 * @var \DynamicDB\Entity\Table $table
 * @var \DynamicDB\Entity\DynamicEntity[] $records
 */
?>

<div class="container mt-5">
    <h1>Hello, {{$user->getFullName()}}!</h1>
</div>
<div class="container mt-5">
    <div class="btn-group" role="group" aria-label="Basic example">
        <a type="button" class="btn btn-success" href="/records/add">Add</a>
    </div>
</div>
<div class="container mt-5">
    <?php if (count($records) > 0): ?>
        {{template|'dynamic_db/table_items'|['table' => $table, 'records' => $records]}}
    <?php else: ?>
        <div class="alert alert-primary" role="alert">
            {{lang|"no_items_found"}}
        </div>
    <?php endif; ?>
</div>
