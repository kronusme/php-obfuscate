<?php

use PhpParser\Node;
use PhpParser\Node\Stmt;

class VariableNodeVisitor extends PhpParser\NodeVisitorAbstract
{

    /**
     * List of user defined variables and their pseudo
     * Key - real variable's name, value - pseudo
     * @var array
     */
    protected $variables = array();

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Expr\Variable || $node instanceof Node\Param) {
            if (!array_key_exists($node->name, $this->variables)) {
                $this->variables[$node->name] = uniqid(chr(rand(97, 122)), false);
            }
            $node->name = $this->variables[$node->name];
        }
    }

}