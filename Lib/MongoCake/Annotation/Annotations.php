<?php
namespace Doctrine\ODM\MongoDB\Mapping;

final class HasOneEmbedded extends Field {
    public $type = 'one';
    public $embedded = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
	public $alias;
}
final class HasManyEmbedded extends Field {
    public $type = 'many';
    public $embedded = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $strategy = 'pushAll'; // pushAll, set
	public $alias;
}

final class BelongsTo extends Field {
    public $type = 'one';
    public $reference = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $cascade;
    public $inversedBy;
    public $mappedBy;
    public $repositoryMethod;
    public $sort = array();
    public $criteria = array();
    public $limit;
    public $skip;
	public $alias;
	public $belongsTo = true;
}
final class HasOne extends Field {
    public $type = 'one';
    public $reference = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $cascade;
    public $inversedBy;
    public $mappedBy;
    public $repositoryMethod;
    public $sort = array();
    public $criteria = array();
    public $limit;
    public $skip;
	public $alias;
}
final class HasMany extends Field {
    public $type = 'many';
    public $reference = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $cascade;
    public $inversedBy;
    public $mappedBy;
    public $repositoryMethod;
    public $sort = array();
    public $criteria = array();
    public $limit;
    public $skip;
    public $strategy = 'pushAll'; // pushAll, set
	public $alias;
}