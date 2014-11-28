<?php

use PhpParser\Node;

class StringNodeVisitor extends PhpParser\NodeVisitorAbstract
{
    public function leaveNode(Node $node) {
        if ($node instanceof Node\Scalar\String) {
            $node->value = $this->obfuscate($node->value);
            $node->obfuscated = true;
        }
    }

    protected function obfuscate($str) {
        $c = array();
        for($i = 0; $i < strlen($str); $i++) {
            array_push($c, "chr(".ord($str[$i]).")");
        }
        return count($c) > 0 ? implode('.', $c) : '';
    }
}