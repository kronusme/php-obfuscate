<?php

use PhpParser\Node;

class Function2NodeVisitor extends PhpParser\NodeVisitorAbstract
{

    public $nodesWithFunctionNames = null;

    public $userDefinedFunctionNames = null;

    public $currentUserDefined = '';

    public function __construct(array $nodesWithFunctionNames, array $userDefinedFunctionNames) {
        $this->nodesWithFunctionNames = $nodesWithFunctionNames;
        $this->userDefinedFunctionNames = $userDefinedFunctionNames;
    }

    /**
     * When visitor comes on function's declaration save current node-name to <code>currentUserDefined</code>
     * Add functions map for each function body. Inserts in the top of it
     *
     * @param Node $node
     */
    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\Function_) {
            $this->currentUserDefined = $node->name;
            $node->stmts = array_merge(array($this->nodesWithFunctionNames[$this->currentUserDefined]), $node->stmts);
        }
    }

    /**
     * Update <code>currentUserDefined</code>. Remove current function name of it
     * Update each function call. Remove "nesting" like "a|b|c" from it. Leave only "c"
     *
     * @param Node $node
     */
    public function leaveNode(Node $node) {

        if ($node instanceof Node\Stmt\Function_) {
            $name_parts = explode('|', $node->name);
            $node->name = array_pop($name_parts);
            $n = explode('|', $this->currentUserDefined);
            array_pop($n);
            $this->currentUserDefined = implode('|', $n);
        }

        if ($node instanceof Node\Expr\FuncCall) {
            $partsLength = count($node->name->parts);
            $name = $node->name->parts[$partsLength - 1];
            $name_parts = explode('|', $name);
            $node->name->parts[$partsLength - 1] = array_pop($name_parts);
        }

    }
}