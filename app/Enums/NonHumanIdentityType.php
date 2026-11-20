<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A non-human identity is a machine credential, not a person.
 *
 * It must never be used for interactive login, and its secret lives in a secret
 * store. OpenGRC records only the reference and the lifecycle metadata.
 */
enum NonHumanIdentityType: string implements HasColor, HasLabel
{
    case ServiceAccount = 'service_account';
    case ApiToken = 'api_token';
    case BotToken = 'bot_token';
    case IntegrationCredential = 'integration_credential';
    case Certificate = 'certificate';
    case OauthClient = 'oauth_client';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ServiceAccount => 'Service account',
            self::ApiToken => 'API token',
            self::BotToken => 'Bot token',
            self::IntegrationCredential => 'Integration credential',
            self::Certificate => 'Certificate',
            self::OauthClient => 'OAuth client',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ServiceAccount, self::OauthClient => 'info',
            self::ApiToken, self::BotToken, self::IntegrationCredential => 'warning',
            self::Certificate => 'success',
            self::Other => 'gray',
        };
    }
}
