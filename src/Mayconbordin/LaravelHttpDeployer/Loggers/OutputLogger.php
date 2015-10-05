<?php namespace Mayconbordin\LaravelHttpDeployer\Loggers;


use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputLogger implements Logger
{
    /**
     * The output interface implementation.
     *
     * @var \Illuminate\Console\OutputStyle
     */
    protected $output;

    /**
     * @param \Illuminate\Console\OutputStyle $output
     */
    public function __construct($output)
    {
        $this->output = $output;
    }

    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @return void
     */
    public function info($string)
    {
        $this->output->writeln("<info>$string</info>");
    }

    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @return void
     */
    public function error($string)
    {
        $this->output->writeln("<error>$string</error>");
    }

    /**
     * Write a string as warning output.
     *
     * @param  string  $string
     * @return void
     */
    public function warn($string)
    {
        $style = new OutputFormatterStyle('yellow');

        $this->output->getFormatter()->setStyle('warning', $style);

        $this->output->writeln("<warning>$string</warning>");
    }

    /**
     * @param $string
     * @return mixed
     */
    public function plain($string)
    {
        $this->output->write("$string");
    }
}