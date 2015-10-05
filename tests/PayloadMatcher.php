<?php

class PayloadMatcher extends \Mockery\Matcher\MatcherAbstract
{
    /**
     * Check if the actual value matches the expected.
     * Actual passed by reference to preserve reference trail (where applicable)
     * back to the original method parameter.
     *
     * @param mixed $actual
     * @return bool
     */
    public function match(&$actual)
    {
        foreach ($this->_expected as $key) {
            if (!in_array($key, array_keys($actual))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return a string representation of this Matcher
     *
     * @return string
     */
    public function __toString()
    {
        $return = '<PayloadMatcher[' . $this->_expected . ']>';
        return $return;
    }
}