<?php namespace Mayconbordin\LaravelHttpDeployer\Loggers;


interface Logger
{

    /**
     * @param $string
     * @return mixed
     */
    public function plain($string);

    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @return void
     */
    public function info($string);

    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @return void
     */
    public function error($string);

    /**
     * Write a string as warning output.
     *
     * @param  string  $string
     * @return void
     */
    public function warn($string);
}