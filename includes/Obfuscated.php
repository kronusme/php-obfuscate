<?php

namespace PhpParser\PrettyPrinter;

use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Stmt;
use PhpParser\Node\Name;

class Obfuscated extends Standard {

    public function pScalar_String(Scalar\String $node) {
        if ($node->obfuscated)
            return $this->pNoIndent(addcslashes($node->value, '\'\\'));
        return parent::pScalar_String($node);
    }

}