<?php

namespace Lib {
    use Foo\Service\BarService;

    class Braced
    {
        public function build()
        {
            return new BarService();
        }
    }
}
