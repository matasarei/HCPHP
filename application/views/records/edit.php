<div class="container mt-5">
    <?php if (isset($exception)): ?>
        <div class="alert alert-danger" role="alert"><?= $exception->getMessage(); ?></div>
    <?php endif; ?>

    <div class="row">
        {{$form}}
    </div>
</div>
