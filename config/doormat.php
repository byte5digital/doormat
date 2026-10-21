<?php

declare(strict_types=1);

return [
    // The collection the notices are entries of; doormat:install writes its definition.
    'collection' => 'doormat_notices',

    // How {{ doormat_notices }} shows them: 'modal' (a dialog for the notices a visitor may close,
    // bars for the rest) or 'notice' (bars only). A tag parameter view="…" overrides it per placement.
    'view' => 'modal',
];
