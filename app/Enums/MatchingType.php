<?php

namespace App\Enums;

enum MatchingType: string
{
    case Keyword = 'keyword';
    case EvidenceCitation = 'evidence_citation';
    case Manual = 'manual';
}
