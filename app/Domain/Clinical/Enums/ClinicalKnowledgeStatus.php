<?php

namespace App\Domain\Clinical\Enums;

enum ClinicalKnowledgeStatus: string
{
    case Unknown = 'UNKNOWN';
    case NoneKnown = 'NONE_KNOWN';
    case Known = 'KNOWN';
}
