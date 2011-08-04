<?php
namespace Doctrine\ODM\MongoDB\Mapping\Annotations;

/** @Annotation */
final class HasOneEmbedded extends AbstractField {
    public $type = 'one';
    public $embedded = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
	public $alias;
}
/** @Annotation */
final class HasManyEmbedded extends AbstractField {
    public $type = 'many';
    public $embedded = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $strategy = 'pushAll'; // pushAll, set
	public $alias;
}
/** @Annotation */
final class BelongsTo extends AbstractField {
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
/** @Annotation */
final class HasOne extends AbstractField {
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
/** @Annotation */
final class HasMany extends AbstractField {
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