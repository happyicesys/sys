<?php

namespace App\Services\OpsJobStops;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Per-operator running numbers (10001, 10002, …) for ops-job stops. The
 * `(operator_id, code)` unique index is the arbiter: two people opening a
 * notice at the same instant both read the same max, one insert loses, and
 * that one simply tries the next number.
 */
class StopCodeGenerator
{
    private const FIRST_CODE = 10001;

    private const MAX_ATTEMPTS = 5;

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $modelClass
     * @param  Closure(int): T  $create  receives the candidate code, returns the created model
     * @return T
     */
    public function createWithNextCode(string $modelClass, int $operatorId, Closure $create): Model
    {
        $attempt = 0;

        while (true) {
            $code = $this->next($modelClass, $operatorId);

            try {
                return $create($code);
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /** @param  class-string<Model>  $modelClass */
    private function next(string $modelClass, int $operatorId): int
    {
        $max = $modelClass::query()->where('operator_id', $operatorId)->max('code');

        return $max ? ((int) $max) + 1 : self::FIRST_CODE;
    }
}
