<?php

/**
 * Class LockManager
 */
class LockManager
{

    /**
     * @var array
     */
    private $lockedValues = [];

    /**
     * @var \Redis[]
     */
    private $instances = [];

    /**
     * @var int
     */
    private $_quorum;

    /**
     * @var array
     */
    private $serversConfiguration;

    /**
     * @var
     */
    private $retryDelay;

    /**
     * @var
     */
    private $retryCount;

    /**
     * LockManager constructor.
     *
     * @param array $serversConfiguration
     * @param int   $retryCount
     * @param int   $retryDelay
     */
    public function __construct(array $serversConfiguration, $retryCount = 3, $retryDelay = 3)
    {
        $this->serversConfiguration = $serversConfiguration;
        $this->retryCount           = $retryCount;
        $this->retryDelay           = $retryDelay;
    }

    /**
     * Lock a string value until ttl expired or release called
     *
     * @param string $value Value to lock
     * @param int    $ttl   Lock time in second.
     *
     * @return bool
     */
    public function lock($value, $ttl = 5)
    {
        $this->instantiateRedisConnection();
        $token = uniqid('lock-', false);

        do {
            $nbLock = 0;
            foreach ($this->instances as $instance) {
                if ($instance->set($value, $token, ['NX', 'EX' => $ttl])) {
                    $nbLock++;
                }
            }

            if ($nbLock === 0) {
                return false;
            } elseif ($nbLock >= $this->_quorum) {
                $this->lockedValues[$value] = $token;

                return true;
            } else {
                $this->lockedValues[$value] = $token;
                $this->unLock($value);
            }

            $delay = $this->computeRetryDelay();
            usleep($delay * 1000);
            $this->retryCount--;
        } while ($this->retryCount > 0);

        return false;
    }

    /**
     * Release the locked value
     *
     * @param string $value Value to unlock.
     *
     * @return void
     */
    public function unLock($value)
    {
        $this->instantiateRedisConnection();
        if (isset($this->lockedValues[$value])) {
            $script = '
                if redis.call("GET", KEYS[1]) == ARGV[1] then
                    return redis.call("DEL", KEYS[1])
                else
                    return 0
                end
            ';

            foreach ($this->instances as $instance) {
                $instance->eval($script, [$value, $this->lockedValues[$value]], 1);
            }
        }
    }

    /**
     * @return void
     */
    private function instantiateRedisConnection()
    {
        if (empty($this->instances)) {
            $driverGenerator = $this->connectionGenerator();
            $this->instances = iterator_to_array($driverGenerator);
            $this->_quorum   = min(count($this->instances), (count($this->instances) / 2 + 1));
        }
    }

    /**
     * @return Generator
     */
    private function connectionGenerator()
    {
        foreach ($this->serversConfiguration as list($host, $port, $timeout)) {
            $redisClient = new  \Redis();
            try {
                $redisClient->connect($host, $port, $timeout);
                $redisClient->ping();
            } catch (\Exception $e) {
                continue;
            }
            yield $redisClient;
        }
    }

    /**
     * @return int
     */
    private function computeRetryDelay()
    {
        $retryDelay = $this->retryDelay * 100;

        // Wait a random delay before retry
        return mt_rand(floor($retryDelay / 2), $retryDelay);
    }
}