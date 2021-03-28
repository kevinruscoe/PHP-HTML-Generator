<?php

use loophp\phptree\Exporter\ExporterInterface;
use loophp\phptree\Node\KeyValueNode;
use loophp\phptree\Node\NodeInterface;
use loophp\phptree\Node\ValueNode;

include './vendor/autoload.php';

interface ElementInterface 
{
    public function openTag() : string;
    public function renderBlock() : string;
    public function closeTag() : string;
}

class HtmlElement implements ElementInterface 
{
    public string $type;
    public array $attributes;
    public ?HtmlBlockInterface $block;
    
    public function __construct(string $type = "div", array $attributes = [], ?HtmlBlockInterface $block = null) 
    {
        $this->type = $type;
        $this->attributes = $attributes;
        $this->block = $block;
    }

    public function openTag() : string
    {
        return sprintf("<%s%s>", $this->type, $this->parseAttributes());
    }

    public function renderBlock() : string
    {
        if (! $this->block) {
            return '';
        }

        return $this->block->render();
    }

    public function closeTag() : string
    {
        return sprintf("</%s>", $this->type);
    }

    private function parseAttributes()
    {
        return join("", array_map(function($key, $value){
            if (is_numeric($key)) {
                return sprintf(" %s", $value);
            }
            
            return sprintf(" %s='%s'", $key, $value);
        }, array_keys($this->attributes), $this->attributes));
    }
}

class SelfClosingHtmlElement extends HtmlElement {
    public function closeTag() : string
    {
        return '';
    }
}

class TaglessElement implements ElementInterface 
{
    public ?HtmlBlockInterface $block;
    
    public function __construct(?HtmlBlockInterface $block = null) 
    {
        $this->block = $block;
    }

    public function renderBlock() : string
    {
        if (! $this->block) {
            return '';
        }

        return $this->block->render();
    }

    public function closeTag() : string
    {
        return '';
    }

    public function openTag() : string
    {
        return '';
    }
}

interface HtmlBlockInterface 
{
    public function render() : string;
}

class FavouriteBlock implements HtmlBlockInterface
{
    public function render() : string
    {
        return "FavouriteBlock";
    }
}

class WishlistBlock implements HtmlBlockInterface
{
    public function render() : string
    {
        return "WishlistBlock";
    }
}

class TextBlock implements HtmlBlockInterface
{
    public string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public function render() : string
    {
        return $this->text;
    }
}

class HtmlExporter implements ExporterInterface
{
    public function export(NodeInterface $node) : string
    {
        $document = $node->getValue()->openTag();
        $document .= $node->getValue()->renderBlock();

        foreach ($node->children() as $child) {
            $document .= $this->export($child);
        }

        $document .= $node->getValue()->closeTag();

        return $document;
    }
}

class DocumentRenderer
{
    public NodeInterface $document;

    public function __construct(NodeInterface $document)
    {
        $this->document = $document;
    }

    public function render() : string
    {
        return (new HtmlExporter)->export($this->document);
    }

    public function appendNode(string $target, NodeInterface $node) : void
    {
        $targetNode = $this->findNodeByName($target);

        if ($targetNode) {
            $targetNode->add($node);
        }
    }

    private function findNodeByName(string $name, ?NodeInterface $node = null) : NodeInterface|false
    {
        if (! $node) {
            $node = $this->document;
        }

        if (get_class($node) === 'loophp\phptree\Node\KeyValueNode') {
            if ($node->getKey() === $name) {
                return $node;
            }
        }

        foreach ($node->children() as $childNode) {
            $node = $this->findNodeByName($name, $childNode);

            if ($node) {
                return $node;
            }
        }

        return false;
    }
}

// setup standard doc
$document = (new ValueNode(new TaglessElement))->add(
    new ValueNode(new SelfClosingHtmlElement('!DOCTYPE', ['html'])),
    (new ValueNode(new HtmlElement('html', ['lang' => 'en'])))->add(
        (new KeyValueNode('head', new HtmlElement('head')))->add(
            (new ValueNode(new SelfClosingHtmlElement('meta', ['charset' => 'UTF-8']))),
            (new ValueNode(new SelfClosingHtmlElement(
                'meta', ['http-equiv' => 'X-UA-Compatible', 'content' => 'IE=edge']
            ))),
            (new ValueNode(new HtmlElement(
                type: 'title', 
                block: new TextBlock("Yolo")
            ))),
        ),
        new KeyValueNode('body', new HtmlElement('body'))
    )
);


$documentRenderer = new DocumentRenderer($document);

// add container, sidebar and contnet
$documentRenderer->appendNode(
    'body', 
    (new ValueNode(new HtmlElement(attributes: ['class' => 'container', 'id' => 'container'])))->add(
        (new KeyValueNode('sidebar', new HtmlElement(attributes: ['class' => 'sidebar'])))->add(
            new KeyValueNode('wishlist', new HtmlElement(
                attributes: ['class' => 'wishlist'], 
                block: new WishlistBlock()
            ))
        ),
        new KeyValueNode('content', new HtmlElement(attributes: ['class' => 'content']))
    )
);

// add favs
$documentRenderer->appendNode(
    'sidebar',
    new KeyValueNode('favourite', new HtmlElement(
        attributes: ['class' => 'favourites'], 
        block: new FavouriteBlock()
    ))
);

// add style in head
$documentRenderer->appendNode(
    'head', 
    new KeyValueNode('style', new HtmlElement(type: 'style'))
);

// add sme CSS
$documentRenderer->appendNode(
    'style', 
    new ValueNode(new TaglessElement(
        new TextBlock(
            <<<EOT

                .container {
                    width: 800px;
                    margin: 0 auto;
                    border: 1px solid red;
                    display: flex;
                }

                .container .sidebar {
                    width: 25%;
                    border: 1px solid blue;
                }

                .container .content {
                    width: 75%;
                }

            EOT
        )
    ))
);

// print the html
print $documentRenderer->render($document);

