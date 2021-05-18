<?php

namespace api\controllers\shop;

use api\providers\MapDataProvider;
use common\models\Category;
use common\models\Product\Product;
use common\models\Tag;
use common\models\readModels\CategoryReadRepository;
use common\models\readModels\TagReadRepository;
use common\models\readModels\ProductReadRepository;
use yii\data\DataProviderInterface;
use yii\helpers\Url;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;

class ProductController extends Controller
{
    private $items;
    private $categories;
    private $tags;

    public function __construct(
        $id,
        $module,
        ProductReadRepository $items,
        CategoryReadRepository $categories,
        TagReadRepository $tags,
        $config = []
    )
    {
        parent::__construct($id, $module, $config);
        $this->items = $items;
        $this->categories = $categories;
        $this->tags = $tags;
    }

    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'category' => ['GET'],
            'tag' => ['GET'],
            'view' => ['GET'],
        ];
    }

    /**
     * @SWG\Get(
     *     path="/items",
     *     tags={"Catalog"},
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/ProductItem")
     *         ),
     *     ),
     *     security={{"Bearer": {}, "OAuth2": {}}}
     * )
     */
    public function actionIndex(): DataProviderInterface
    {
        $dataProvider = $this->products->getAll();
        return new MapDataProvider($dataProvider, [$this, 'serializeListItem']);
    }

    /**
     * @SWG\Get(
     *     path="/items/category/{categoryId}",
     *     tags={"Catalog"},
     *     @SWG\Parameter(name="categoryId", in="path", required=true, type="integer"),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         @SWG\Schema(ref="#/definitions/ProductItem")
     *     ),
     *     security={{"Bearer": {}, "OAuth2": {}}}
     * )
     * @param $id
     * @return DataProviderInterface
     * @throws NotFoundHttpException
     */
    public function actionCategory($id): DataProviderInterface
    {
        if (!$category = $this->categories->find($id)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $dataProvider = $this->products->getAllByCategory($category);
        return new MapDataProvider($dataProvider, [$this, 'serializeListItem']);
    }

    /**
     * @SWG\Get(
     *     path="/items/tag/{tagId}",
     *     tags={"Catalog"},
     *     @SWG\Parameter(name="tagId", in="path", required=true, type="integer"),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         @SWG\Schema(ref="#/definitions/ProductItem")
     *     ),
     *     security={{"Bearer": {}, "OAuth2": {}}}
     * )
     * @param $id
     * @return DataProviderInterface
     * @throws NotFoundHttpException
     */
    public function actionTag($id): DataProviderInterface
    {
        if (!$tag = $this->tags->find($id)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $dataProvider = $this->products->getAllByTag($tag);
        return new MapDataProvider($dataProvider, [$this, 'serializeListItem']);
    }

    /**
     * @SWG\Get(
     *     path="/items/{productId}",
     *     tags={"Catalog"},
     *     @SWG\Parameter(
     *         name="productId",
     *         description="ID of product",
     *         in="path",
     *         required=true,
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Success response",
     *         @SWG\Schema(ref="#/definitions/ProductView")
     *     ),
     *     security={{"Bearer": {}, "OAuth2": {}}}
     * )
     * 
     * @param $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionView($id): array
    {
        if (!$product = $this->items->find($id)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        return $this->serializeView($product);
    }

    public function serializeListItem(Item $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'category' => [
                'id' => $item->category->id,
                'name' => $item->category->name,
                '_links' => [
                    'self' => ['href' => Url::to(['category', 'id' => $item->category->id], true)],
                ],
            ],
            'price' => [
                'new' => $item->price_new,
                'old' => $item->price_old,
            ],
            'thumbnail' => $item->mainPhoto ? $item->mainPhoto->getThumbFileUrl('file', 'catalog_list'): null,
            '_links' => [
                'self' => ['href' => Url::to(['view', 'id' => $item->id], true)],
                'stock' => ['href' => Url::to(['/item/add', 'id' => $item->id], true)],
            ],
        ];
    }

    private function serializeView(Item $item): array
    {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'categories' => [
                'main' => [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                    '_links' => [
                        'self' => ['href' => Url::to(['category', 'id' => $item->category->id], true)],
                    ],
                ],
                'other' => array_map(function (Category $category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        '_links' => [
                            'self' => ['href' => Url::to(['category', 'id' => $category->id], true)],
                        ],
                    ];
                }, $item->categories),
            ],
            'tags' => array_map(function (Tag $tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    '_links' => [
                        'self' => ['href' => Url::to(['tag', 'id' => $tag->id], true)],
                    ],
                ];
            }, $product->tags),
            'price' => [
                'new' => $product->price_new,
                'old' => $product->price_old,
            ],
        ];
    }
}

/**
 * @SWG\Definition(
 *     definition="ProductItem",
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="code", type="string"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="category", ref="#/definitions/ProductCategory"),
 *     @SWG\Property(property="price", ref="#/definitions/ProductPrice"),
 *     @SWG\Property(property="thumbnail", type="string"),
 *     @SWG\Property(property="_links", type="object",
 *         @SWG\Property(property="self", type="object", @SWG\Property(property="href", type="string")),
 *     ),
 * )
 *
 * @SWG\Definition(
 *     definition="ProductView",
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="code", type="string"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="description", type="string"),
 *     @SWG\Property(property="categories", type="object",
 *         @SWG\Property(property="main", ref="#/definitions/ProductCategory"),
 *         @SWG\Property(property="other", type="array", @SWG\Items(ref="#/definitions/ProductCategory")),
 *     ),
 * )
 *
 * @SWG\Definition(
 *     definition="ItemCategory",
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="_links", type="object",
 *         @SWG\Property(property="self", type="object", @SWG\Property(property="href", type="string")),
 *     ),
 * )
 *
 * @SWG\Definition(
 *     definition="ItemTag",
 *     type="object",
 *     @SWG\Property(property="id", type="integer"),
 *     @SWG\Property(property="name", type="string"),
 *     @SWG\Property(property="_links", type="object",
 *         @SWG\Property(property="self", type="object", @SWG\Property(property="href", type="string")),
 *     ),
 * )
 *
 * @SWG\Definition(
 *     definition="ItemPrice",
 *     type="object",
 *     @SWG\Property(property="new", type="integer"),
 *     @SWG\Property(property="old", type="integer"),
 * )
 */