<?php

final class PortalMobileNumberException extends DomainException {}

/** Profile input accepts spaces/hyphens; persistence always uses local format. */
function portalNormalizeMobileNumber(mixed $input): ?string
{
    $message = 'Enter a valid Philippine mobile number: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX or 9XXXXXXXXX. Spaces and hyphens are allowed.';
    if ($input === null) return null;
    if (!is_string($input)) throw new PortalMobileNumberException($message);
    $number = str_replace([' ', '-'], '', $input);
    if (trim($input, ' ') === '') return null;
    if (!preg_match('/^(?:0|\+63|63)?9[0-9]{9}$/D', $number)) {
        throw new PortalMobileNumberException($message);
    }
    return '0' . substr($number, -10);
}
