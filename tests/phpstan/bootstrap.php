<?php

use Statamic\Entries\Entry;

// Notice's parent is an alias of the entry class the application binds (src/Entries/Notice.php);
// for the analysis it is Statamic's own.
class_alias(Entry::class, 'Byte5\Doormat\Entries\NoticeBase');
