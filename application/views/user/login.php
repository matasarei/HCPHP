<div class="container mt-5">
    <div class="row">
        <div class="col-lg-4 offset-lg-4">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    {{lang|$error}}
                </div>
            <?php endif;?>

            {{$form}}
        </div>
    </div>
</div>
