<?php

use infrajs\template\Template;

$data = [];
echo Template::parse('-template/tests/nest-use.tpl', $data);