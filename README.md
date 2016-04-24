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

**Файл с шаблоном**

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
require_once('vendor/autoload.php');
$src = 'Путь/до/шаблона';
$html = Template::parse($src, $data);
echo $html; //Привет Алибаба!
```
