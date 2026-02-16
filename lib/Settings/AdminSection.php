<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {

    private IL10N $l;
    private IURLGenerator $url;

    public function __construct(IL10N $l, IURLGenerator $url) {
        $this->l = $l;
        $this->url = $url;
    }

    public function getID(): string {
        return 'circlesadmin';
    }

    public function getName(): string {
        return $this->l->t('Circles Admin');
    }

    public function getPriority(): int {
        return 90;
    }

    public function getIcon(): string {
        return $this->url->imagePath('circles', 'circles.svg');
    }
}
