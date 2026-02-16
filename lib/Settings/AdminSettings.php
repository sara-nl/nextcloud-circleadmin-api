<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

    public function getForm(): TemplateResponse {
        return new TemplateResponse('circlesadmin', 'admin');
    }

    public function getSection(): string {
        return 'circlesadmin';
    }

    public function getPriority(): int {
        return 50;
    }
}
