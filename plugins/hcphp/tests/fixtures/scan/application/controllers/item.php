<?php

class ItemController extends core\Controller
{
    public function actionSave()
    {
        $item = $this->repository->get(Globals::optional('id'));
        $item->setName(Globals::post('name'));

        try {
            $this->repository->save($item);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
