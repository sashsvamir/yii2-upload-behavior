<?php
/**
 * @author Alexey Samoylov <alexey.samoylov@gmail.com>
 * @link http://yiidreamteam.com/yii2/upload-behavior
 */

namespace sashsvamir\upload;

use yii\imagine\Image;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

/**
 * Class ImageUploadBehavior
 */
class ImageUploadBehavior extends FileUploadBehavior
{
    public $attribute = 'image';

	/** @var string Placeholder image */
	public $placeholder = null;

    public $createThumbsOnSave = true;
    public $createThumbsOnRequest = false;

    /** @var array Thumbnail profiles, array of [width, height, ... PHPThumb options] */
    public $thumbs = [];

    /** @var string Path template for thumbnails. Please use the [[profile]] placeholder. */
    public $thumbPath = '@webroot/images/[[profile]]_[[pk]].[[extension]]';
    /** @var string Url template for thumbnails. */
    public $thumbUrl = '/images/[[profile]]_[[pk]].[[extension]]';

    public $filePath = '@webroot/images/[[pk]].[[extension]]';
    public $fileUrl = '/images/[[pk]].[[extension]]';

    /**
     * @inheritdoc
     */
    public function events()
    {
        return ArrayHelper::merge(parent::events(), [
            static::EVENT_AFTER_FILE_SAVE => 'afterFileSave',
        ]);
    }

	/**
	 * Remove image: delete thumbs images, set attribute to null and save model
	 */
	public function removeFile()
	{
		$this->cleanFiles();
		$this->owner->{$this->attribute} = null;
		$this->owner->update(false, [$this->attribute]);
	}

	/**
	 * @inheritdoc
	 */
	public function cleanFiles()
	{
		parent::cleanFiles();
		foreach (array_keys($this->thumbs) as $profile) {
			@unlink($this->getThumbFilePath($this->attribute, $profile));
		}
	}

    /**
     * Resolves profile path for thumbnail profile.
     *
     * @param string $path
     * @param string $profile
     * @return string
     */
    public function resolveProfilePath($path, $profile)
    {
        $path = $this->resolvePath($path);
        return preg_replace_callback('|\[\[([\w\_/]+)\]\]|', function ($matches) use ($profile) {
            $name = $matches[1];
            switch ($name) {
                case 'profile':
                    return $profile;
            }
            return '[[' . $name . ']]';
        }, $path);
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @return string
     */
    public function getThumbFilePath($attribute, $profile = 'thumb')
    {
        $behavior = static::getInstance($this->owner, $attribute);
        return $behavior->resolveProfilePath($behavior->thumbPath, $profile);
    }

    /**
     *
     * @param string $attribute
     * @param string|null $emptyUrl
     * @return string|null
     */
    public function getImageFileUrl($attribute, $emptyUrl = null)
    {
        if (!$this->owner->{$attribute}) {
			return (isset($emptyUrl)) ? $emptyUrl : $this->placeholder;
		}

        return $this->getUploadedFileUrl($attribute);
    }

    /**
     * @param string $attribute
     * @param string $profile
     * @param string|null $emptyUrl
     * @return string|null
     */
    public function getThumbFileUrl($attribute, $profile = 'thumb', $emptyUrl = null)
    {
        if (!$this->owner->{$attribute}) {
			return (isset($emptyUrl)) ? $emptyUrl : $this->placeholder;
		}

        $behavior = static::getInstance($this->owner, $attribute);
        if ($behavior->createThumbsOnRequest)
            $behavior->createThumbs();
        return $behavior->resolveProfilePath($behavior->thumbUrl, $profile);
    }

    /**
     * After file save event handler.
     */
    public function afterFileSave()
    {
        if ($this->createThumbsOnSave == true)
            $this->createThumbs();
    }

    /**
     * Creates image thumbnails
     */
    public function createThumbs()
    {
        $path = $this->getUploadedFilePath($this->attribute);
        foreach ($this->thumbs as $profile => $options) {
            $thumbPath = static::getThumbFilePath($this->attribute, $profile);
            if (is_file($path) && !is_file($thumbPath)) {

				if (!isset($options['mode'])) {
					$options['mode'] = 'outbound'; // "outbound": обрежет края, "inset": вместит картинку добавив пустые поля
				}
				if (!isset($options['jpegQuality'])) {
					$options['jpegQuality'] = 90;
				}

                FileHelper::createDirectory(pathinfo($thumbPath, PATHINFO_DIRNAME), 0775, true);

				Image::thumbnail($path, $options['width'], $options['height'], $options['mode'])->save($thumbPath, ['quality' => $options['jpegQuality']]);
            }
        }
    }
}
