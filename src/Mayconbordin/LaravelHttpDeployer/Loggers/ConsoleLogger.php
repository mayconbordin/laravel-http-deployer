<?php namespace Mayconbordin\LaravelHttpDeployer\Loggers;


class ConsoleLogger implements Logger
{
    /**
     * Write a string as information output.
     *
     * @param  string $string
     * @return void
     */
    public function info($string)
    {
        echo $string . "\n";
    }

    /**
     * Write a string as error output.
     *
     * @param  string $string
     * @return void
     */
    public function error($string)
    {
        echo $string . "\n";
    }

    /**
     * Write a string as warning output.
     *
     * @param  string $string
     * @return void
     */
    public function warn($string)
    {
        echo $string . "\n";
    }

    /**
     * @param $string
     * @return mixed
     */
    public function plain($string)
    {
        echo $string;
    }
}