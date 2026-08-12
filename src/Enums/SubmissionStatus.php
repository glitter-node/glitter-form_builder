<?php

namespace Modules\Glitter\FormBuilder\Enums;

enum SubmissionStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case ProcessingFailed = 'processing_failed';
    case Cancelled = 'cancelled';
}
