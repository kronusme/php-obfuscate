<?php

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;

class FunctionNodeVisitor extends PhpParser\NodeVisitorAbstract
{

    /**
     * List of user-defined function names and their pseudo
     * Key - real name, value - pseudo
     *
     * @var array
     */
    protected $userDefinedFunctionNames = array();

    /**
     * List of used native function names and their pseudo
     * Key - real name, value - pseudo
     *
     * @var array
     */
    protected $nativeFunctionNames = array();

    /**
     * List of using native function in the user defined functions
     * array(
     *  '' => array('pseudo1' => 'strlen', 'pseudo2' => 'str_pos', ...),
     *  'userFuncPseudo1' => array('pseudo3' => 'strrev'),
     *  'userFuncPseudo1|userFuncPseudo2' => array('pseudo4' => 'str_rot13'),
     *  ...
     * )
     * Note: userFunc2 is declared in the userFunc1
     *
     * @var array
     */
    protected $nativeInUserDefined = array(
        '' => array()
    );

    /**
     * Current user-defined function name
     * If nested: funcA|funcB|funcC
     *
     * @var string
     */
    protected $currentUserDefined = '';

    /**
     * @var string
     */
    protected $nativeFunctionNamesStorageKey = null;

    public function __construct() {
        $this->nativeFunctionNamesStorageKey = $this->getUniqid();
    }

    public function getNativeFunctionNames() {
        return $this->nativeFunctionNames;
    }

    public function getUserDefinedFunctionNames() {
        return $this->userDefinedFunctionNames;
    }

    public function getNativeInUserDefined() {
        return $this->nativeInUserDefined;
    }

    /**
     * Generates new unique identifier for pseudos
     * Based on <code>uniqid</code>
     *
     * @return string
     */
    public function getUniqid() {
        return uniqid(chr(rand(97, 122)), false);
    }

    /**
     * Get expression for string representation
     * Node\Scalar\String for empty string ('')
     * Expr\FuncCall for one-character string (chr(100))
     * Node\Expr\BinaryOp\Concat for other cases (chr(100).chr(103). ...)
     *
     * @param string $str
     * @return Expr\BinaryOp\Concat|Expr\FuncCall|Node\Scalar\String
     */
    protected function getNodesForString($str) {
        if ($str === '') return new Node\Scalar\String('');
        $chr = new Node\Expr\FuncCall(
            new Node\Name('chr'),
            array(
                new Node\Arg(
                    new Node\Scalar\LNumber(ord($str[0]))
                )
            )
        );
        if (strlen($str) === 1) return $chr;
        $expr = $chr;
        for($i = 1; $i < strlen($str); $i++) {
            $expr = new Node\Expr\BinaryOp\Concat(
                $expr,
                new Node\Expr\FuncCall(
                    new Node\Name('chr'),
                    array(
                        new Node\Arg(
                            new Node\Scalar\LNumber(ord($str[$i]))
                        )
                    )
                )
            );
        }
        return $expr;
    }

    /**
     * Get list of Nodes for used function in all scopes
     * Structure is based on <code>nativeInUserDefined</code>
     *
     * @return array
     */
    public function getNativeFunctionNamesNode() {
        $arrayItems = array();
        foreach($this->nativeInUserDefined as $userDefined => $natives) {
            $a = array();
            foreach($natives as $native) {
                array_push($a, new Node\Expr\ArrayItem(
                    $this->getNodesForString($native),
                    //new Node\Scalar\String($native),
                    new Node\Scalar\String($this->nativeFunctionNames[$native])
                ));
            }
            $key = $userDefined == '' ? '' : $this->userDefinedFunctionNames[$userDefined];
            $arrayItems[$key] = new Node\Expr\Assign(
                new Node\Expr\Variable($this->nativeFunctionNamesStorageKey),
                new Node\Expr\Array_($a)
            );
        }
        return $arrayItems;
    }

    /**
     * When visitor comes on function's declaration (Node\Stmt\Function_) node, this function
     * should be saved into <code>userDefinedFunctionNames</code>,
     * <code>currentUserDefined</code> should be updated with current function name,
     * new value added to <code>nativeInUserDefined</code> with key equal to <code>currentUserDefined</code>,
     * pseudo for this function's name should be added to <code>userDefinedFunctionNames</code>
     *
     * @param Node $node
     */
    public function enterNode(Node $node) {
        // function declaration
        if ($node instanceof Node\Stmt\Function_) {

            //some "magic" with nested names
            $parts = explode('|', $this->currentUserDefined);
            if (count($parts) == 1 && $parts[0] === '') {
                $this->currentUserDefined = $node->name;
            }
            else {
                $this->currentUserDefined = implode('|', array_merge(explode('|', $this->currentUserDefined), array($node->name)));
            }

            $pseudo = array_key_exists($this->currentUserDefined, $this->userDefinedFunctionNames) ?
                $this->userDefinedFunctionNames[$this->currentUserDefined]: '';

            $this->nativeInUserDefined[$this->currentUserDefined] = array();
            if (!array_key_exists($this->currentUserDefined, $this->userDefinedFunctionNames)) {
                $this->userDefinedFunctionNames[$this->currentUserDefined] = (strlen($pseudo) > 0 ? $pseudo.'|' : '').$this->getUniqid();
            }
            $node->name = $this->userDefinedFunctionNames[$this->currentUserDefined];
        }
    }

    /**
     * When visitor navigates away from function's declaration (Node\Stmt\Function_),
     * it's name should be removed from <code>currentUserDefined</code>
     *
     * When visitor navigates away from function's call (Node\Expr\FuncCall),
     * it's name should be obfuscated and replaced with call like:
     * <code>
     *  strlen($a);
     * </code>
     * becomes to:
     * <code>
     *  $var = array('abc' => 'strlen');
     *  $var['abc]($a);
     * </code>
     * @param Node $node
     */
    public function leaveNode(Node $node) {
        if ($node instanceof Node\Stmt\Function_) {
            $n = explode('|', $this->currentUserDefined);
            array_pop($n);
            $this->currentUserDefined = implode('|', $n);
        }

        // function call
        if ($node instanceof Node\Expr\FuncCall) {
            $partsLength = count($node->name->parts);
            $name = $node->name->parts[$partsLength - 1];
            $key = (strlen($this->currentUserDefined) > 0 ? $this->currentUserDefined.'|' : '').$name;
            if (array_key_exists($key, $this->userDefinedFunctionNames)) {
                // user defined function
                $node->name->parts[$partsLength - 1] = $this->userDefinedFunctionNames[$key];
            }
            else {
                // native function
                if (!array_key_exists($name, $this->nativeFunctionNames)) {
                    $this->nativeFunctionNames[$name] = $this->getUniqid();
                }
                if (!in_array($node->name->parts[$partsLength - 1], $this->nativeInUserDefined[$this->currentUserDefined])) {
                    array_push($this->nativeInUserDefined[$this->currentUserDefined], $node->name->parts[$partsLength - 1]);
                }
                $node->name->parts[$partsLength - 1] = '$'.$this->nativeFunctionNamesStorageKey.'[\''.$this->nativeFunctionNames[$name].'\']';
            }
        }
    }

}