<?php

namespace Html;

use core\Template;

interface TemplateAware
{
    public function setTemplate(Template $template);
}
