<?php
// Independent server-only authorization; never inherited from application/preview flags.
return ['enabled'=>getenv('MCO_STAGE1_DISPLAY_ENABLED')==='1',
    'development'=>in_array(getenv('APP_ENV') ?: getenv('MCO_APP_ENV'),['local','development'],true)];
