<?php
/**
 *
 * Article is passed by reference, but after the save, so no changes will be saved.
 * Method is called right after the content is saved
 *
 * @param   string   $context  The context of the content passed to the plugin (added in 1.6)
 * @param   object   $article  A JTableContent object
 * @param   boolean  $isNew    If the content is just about to be created
 *
 * @return  boolean   true if function not enabled, is in front-end or is new. Else true or
 *                    false depending on success of save function.
 *
 * @since   1.6
 */
defined('_JEXEC') or die;

class PlgContentOpengraphsnippet extends JPlugin {
    public function onContentBeforeSave($context, $article, $isNew) {
        //Turn on all error reporting so we can see what's wrong
        error_reporting(E_ALL);
        
        //Grab all the categories we've selected in our plugin
        $cats = $this->params->def('cats', array());
        
        //If the current article is in one of those categories
        //Let's grab the Open Graph metadata
        if (in_array($article->catid, $cats)) {
            
            //JSON Decode the images and urls of the article
            $images = json_decode($article->images);
            $urls   = json_decode($article->urls);
            
            //If either the are empty, let's get the tags
            //OR if the introtext is empty, let's get the tags
            if (!empty($urls->urla) && (empty($images->image_intro) || empty($article->introtext))) {
                
                //Get Open Graph Meta Data
                $data = file_get_contents($urls->urla);
                $dom  = new DomDocument();
                $dom->loadHTML($data);
                
                $xpath = new DOMXPath($dom);
                # query metatags with og prefix
                $metas = $xpath->query('//*/meta[starts-with(@property, \'og:\')]');
                
                $twitter = false;
                if ($metas->length == 0) {
                    $metas   = $xpath->query('//*/meta[starts-with(@name, \'twitter:\')]');
                    $twitter = true;
                }
                
                if ($metas->length == 0 && empty($article->introtext)) {
                    $article->introtext = $data;
                }
                
                if ($metas->length == 0) {
                    return true;
                }
                
                $og = array();
                
                //Loop through all of the tags to add to $og variable
                foreach ($metas as $meta) {
                    
                    # get property name without og: prefix
                    $property = str_replace($twitter ? 'twitter:' : 'og:', '', $meta->getAttribute($twitter ? 'name' : 'property'));
                    
                    # get content
                    $content       = $meta->getAttribute('content');
                    $og[$property] = $content;
                    
                }
                
                if ($twitter && !isset($og['image'])) {
                    $og['image'] = $og['image:src'];
                }
                
                if (isset($og['title'])) {
                    $article->title = $og['title'];
                }
                
                //If the image_intro is empty, and we have an Open Graph image
                //Let's set the article's image_intro
                if (empty($images->image_intro) && isset($og['image'])) {
                    $filename = '/images/' . $article->alias . '-' . $article->catid . '.jpg';
                    $this->thumbnail($og['image'], '..' . $filename);
                    $images->image_intro = $filename;
                    $article->images     = json_encode($images);
                }
                
                //If the introtext is empty and we have an Open Graph description
                //Let's set the article's introtext—wrapping it with paragraph tags
                if (empty($article->introtext) && isset($og['description'])) {
                    $article->introtext = '<p>' . $og['description'] . '</p>';
                }
            }
        }
        
        return true;
    }
    
    function thumbnail($url, $filename, $width = 120, $height = true) {
        
        // download and create gd image
        $image = ImageCreateFromString(file_get_contents($url));
        
        // calculate resized ratio
        // Note: if $height is set to TRUE then we automatically calculate the height based on the ratio
        $height = $height === true ? (ImageSY($image) * $width / ImageSX($image)) : $height;
        
        // create image 
        $output = ImageCreateTrueColor($width, $height);
        ImageCopyResampled($output, $image, 0, 0, 0, 0, $width, $height, ImageSX($image), ImageSY($image));
        
        // save image
        ImageJPEG($output, $filename, 95);
        
        // return resized image
        return $output; // if you need to use it
    }
}
?>
