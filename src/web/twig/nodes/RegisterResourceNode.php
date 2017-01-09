<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\web\twig\nodes;

use craft\web\View;
use yii\base\NotSupportedException;

/**
 * Class RegisterResourceNode
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 *
 * @todo   super hacky that this extends Twig_Node_Set, but that's the only way to get Twig_Parser::filterBodyNodes() to leave us alone
 */
class RegisterResourceNode extends \Twig_Node_Set
{
    // Public Methods
    // =========================================================================

    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * Constructor.
     *
     * The nodes are automatically made available as properties ($this->node).
     * The attributes are automatically made available as array items ($this['name']).
     *
     * @param array       $nodes      An array of named nodes
     * @param array       $attributes An array of attributes (should not be nodes)
     * @param int         $lineno     The line number
     * @param string|null $tag        The tag name associated with the Node
     */
    public function __construct(array $nodes = [], array $attributes = [], int $lineno = 0, string $tag = null)
    {
        // Bypass Twig_Node_Set::__construct()
        \Twig_Node::__construct($nodes, $attributes, $lineno, $tag);
    }

    /**
     * @inheritdoc
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $method = $this->getAttribute('method');
        $position = $this->getAttribute('position');
        $value = $this->getNode('value');
        $options = $this->hasNode('options') ? $this->getNode('options') : null;

        $compiler->addDebugInfo($this);

        if ($this->getAttribute('capture')) {
            $compiler
                ->write("ob_start();\n")
                ->subcompile($value)
                ->write("Craft::\$app->getView()->{$method}(ob_get_clean()");
        } else {
            $compiler
                ->write("Craft::\$app->getView()->{$method}(")
                ->subcompile($value);
        }

        if ($position === null && $this->getAttribute('allowPosition')) {
            if ($this->getAttribute('first')) {
                // TODO: Remove this in Craft 4, along with the deprecated `first` param
                $position = 'head';
            } else {
                // Default to endBody
                $position = 'endBody';
            }
        }

        if ($position !== null) {
            // Figure out what the position's PHP value is
            switch ($position) {
                case 'head':
                    $positionPhp = View::POS_HEAD;
                    break;
                case 'beginBody':
                    $positionPhp = View::POS_BEGIN;
                    break;
                case 'endBody':
                    $positionPhp = View::POS_END;
                    break;
                case 'ready':
                    $positionPhp = View::POS_READY;
                    break;
                case 'load':
                    $positionPhp = View::POS_LOAD;
                    break;
                default:
                    throw new NotSupportedException($position.' is not a valid position');
            }
        }

        if ($this->getAttribute('allowOptions')) {
            if ($position !== null || $options !== null) {
                $compiler->raw(', ');

                // Do we have to merge the position with other options?
                if ($position !== null && $options !== null) {
                    /** @noinspection PhpUndefinedVariableInspection */
                    $compiler
                        ->raw('array_merge(')
                        ->subcompile($options)
                        ->raw(", ['position' => $positionPhp])");
                } else if ($position !== null) {
                    /** @noinspection PhpUndefinedVariableInspection */
                    $compiler
                        ->raw("['position' => $positionPhp]");
                } else {
                    $compiler
                        ->subcompile($options);
                }
            }
        } else if ($position !== null) {
            /** @noinspection PhpUndefinedVariableInspection */
            $compiler->raw(", $positionPhp");
        }

        $compiler->raw(");\n");
    }
}
