<?php

use PhpParser\Node;

class DigitNodeVisitor extends PhpParser\NodeVisitorAbstract
{
    public function leaveNode(Node $node) {
        if ($node instanceof Node\Scalar\LNumber) {
            $node->value = $this->obfuscate($node->value);
        }
    }

    protected function obfuscate($num) {
        if (rand(0, 1) < 0.5)
            return '0x'.dechex($num);
        return $num;
    }
}