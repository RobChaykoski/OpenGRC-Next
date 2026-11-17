<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EvidenceImmutableException extends RuntimeException
{
    public static function onDelete(Model $subject): self
    {
        return new self(sprintf(
            'Evidence %s#%s cannot be deleted in production. Withdraw it with a reason instead.',
            class_basename($subject),
            $subject->getKey()
        ));
    }

    public static function onPathChange(Model $subject): self
    {
        return new self(sprintf(
            'Evidence %s#%s cannot point at a different file in production. Submit a new evidence record instead.',
            class_basename($subject),
            $subject->getKey()
        ));
    }
}
