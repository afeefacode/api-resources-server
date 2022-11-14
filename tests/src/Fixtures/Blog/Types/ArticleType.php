<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Types;

use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\DateAttribute;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\AuthorResource;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\Validators\LinkOneValidator;
use Afeefa\ApiResources\Validator\Validators\StringValidator;

class ArticleType extends ModelType
{
    protected static string $type = 'Blog.Article';

    public static string $ModelClass = Article::class;

    protected function fields(FieldBag $fields): void
    {
        $fields->attribute('title', StringAttribute::class)

            ->attribute('summary', StringAttribute::class)

            ->attribute('content', StringAttribute::class)

            ->attribute('date', DateAttribute::class)

            ->relation('author', AuthorType::class)

            ->relation('tags', Type::list(TagType::class));
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields
            ->attribute('title', function (StringAttribute $attribute) {
                $attribute
                    ->validate(function (StringValidator $v) {
                        $v
                            ->filled()
                            ->min(5)
                            ->max(101);
                    });
            })

            ->attribute('summary', function (StringAttribute $attribute) {
                $attribute
                    ->validate(function (StringValidator $v) {
                        $v
                            ->min(3)
                            ->max(200);
                    });
            })

            ->attribute('content', StringAttribute::class)

            ->attribute('date', DateAttribute::class)

            ->relation('author', Type::link(AuthorType::class), function (Relation $relation) {
                $relation
                    ->required()
                    ->validate(function (LinkOneValidator $v) {
                        $v->filled();
                    })
                    ->optionsRequest(function (ApiRequest $request) {
                        $request
                            ->resourceType(AuthorResource::type())
                            ->actionName('get_authors')
                            ->fields(['name' => true, 'count_articles' => true])
                            ->filters(['page_size' => 100]);
                    });
            })

            ->relation('tags', Type::list(Type::link(TagType::class)));
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields
            ->from($updateFields, 'title', function (Attribute $attribute) {
                $attribute->required();
            })

            ->from($updateFields, 'date', function (Attribute $attribute) {
                $attribute->required();
            })

            ->from($updateFields, 'author', function (Relation $relation) {
                $relation->required();
            });
    }
}
