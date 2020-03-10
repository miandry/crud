# Drupal 8 CRUD 
Simple way to create , update entity . The module can support following field types : paragraph,text 

#1 example 
```php
$fields =['name'=>'john','pass'=>'12345','mail'=>'john@yahoo.fr']
\Drupal::service('crud')->save('user', 'user', $fields);
```
#2 example 
```php
\Drupal::service('crud')->save('taxonomy_term', 'tags',['sport','education']);
```
#Full example
```php

    $fields =  [
        'title' => 'Title Sample ',
        'field_media_image' => [
              ['https://cdn.pixabay.com/photo/2017/01/04/15/18/buttons-1952271_1280.png'],
              ['https://cdn.pixabay.com/photo/2017/01/04/15/18/buttons-1952271_1280.png']
        ],
        'field_tags' => [
            [
                "description" => "test description121231",
                "name" => "txt qww",
                "tid" => 66
            ],
            [
                "description" => "test description121231",
                "name" => "txtww"
            ]
        ],
        'field_user' => 4,
        'field_paragraphs'=>[
            [
                'field_image' => 'https://cdn.pixabay.com/photo/2017/01/04/15/18/buttons-1952271_1280.png',
                'field_tags'=> 'sport'
            ],
            [
                'field_image' => 'https://cdn.pixabay.com/photo/2017/01/04/15/18/buttons-1952271_1280.png',
                'field_tags'=> [12,10]
            ]
        ]
    ];
    \Drupal::service('crud')->save('node', 'article', $fields);
```
