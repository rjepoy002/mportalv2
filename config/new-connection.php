<?php

// Server configuration only: unset, empty and unexpected values fail closed.
// Enable explicitly with MCO_NEW_CONNECTION_ENABLED=1 in the PHP environment.
return ['enabled' => getenv('MCO_NEW_CONNECTION_ENABLED') === '1'];
