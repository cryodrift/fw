<?php

//declare(strict_types=1);

namespace cryodrift\fw\tool;

use cryodrift\fw\trait\DbHelper;
use cryodrift\fw\trait\DbHelperFnkDate;
use cryodrift\fw\trait\DbHelperFnkText;

class DbHelperStatic
{
    use DbHelper;
    use DbHelperFnkDate;
    use DbHelperFnkText;
}
