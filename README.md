# Шаблонизатор Template

## Установка через composer

```json
{
	"require":{
		"infrajs/template":"~1"
	}
}
```

## Использование

**Шаблон**

```html
Привет {name}!
```

**Данные**

```php
$data = array(
	"name"=>"Алибаба"
);
```

**Объединяем**

```php
use infrajs\template\Template;
$src = 'Путь/до/шаблона';
$html = Template::parse($src, $data);
echo $html;
```