<?php

class RedditImageExtension extends Minz_Extension {
    const IMAGE_CONTENT = '<div class="reddit-image figure"><a href="%1$s"><img src="%1$s" class="reddit-image"/></a><p class="caption"><a href="%2$s">Comments</a></p></div>';
    const VIDEO_CONTENT = '<div class="reddit-image figure"><video controls class="reddit-image"><source src="%1$s" type="video/%2$s"></video><p class="caption"><a href="%3$s">Comments</a></p></div>';
    const LINK_CONTENT = '%1$s<p><a href="%2$s">%2$s</a></p>';
    const GFYCAT_API = 'https://api.gfycat.com/v1/gfycats/%s';
    const MATCH_REDDIT = 'reddit.com';

    public function init() {
        $this->registerHook('entry_before_display', array($this, 'transformEntry'));
        $this->registerHook('entry_before_insert', array($this, 'updateGfycatLink'));
    }

    public function transformEntry($entry) {
        if (false === $this->isRedditLink($entry)) {
            return $entry;
        }

        if (null === $href = $this->getContentLink($entry)) {
            return $entry;
        }

        // Add image tag in content when the href links to an image
        if (preg_match('#(jpg|png|gif|bmp)(\?.*)?$#', $href)) {
            $this->addImageContent($entry, $href);
        // Add image tag in content when the href links to an imgur gifv
        } elseif (preg_match('#(?P<gifv>.*imgur.com/[^/]*).gifv$#', $href, $matches)) {
            $href = "${matches['gifv']}.gif";
            $this->addImageContent($entry, $href);
        // Add image tag in content when the href links to an imgur image
        } elseif (preg_match('#(?P<imgur>imgur.com/[^/]*)$#', $href)) {
            $href = "${href}.png";
            $this->addImageContent($entry, $href);
        // Add video tag in content when the href links to a video
        } elseif (preg_match('#(?P<extension>webm|mp4)$#', $href, $matches)) {
            $this->addVideoContent($entry, $href, $matches['extension']);
        } else {
            $this->addLinkContent($entry, $href);
        }

        $entry->_link($href);

        return $entry;
    }

    public function updateGfycatLink($entry) {
        if (false === $this->isRedditLink($entry)) {
            return $entry;
        }

        if (null === $href = $this->getContentLink($entry)) {
            return $entry;
        }

        if (preg_match('#(?P<gfycat>gfycat.com/)(.*/)*(?P<token>[^/.]*)$#', $href, $matches)) {
            try {
                $jsonResponse = file_get_contents(sprintf(static::GFYCAT_API, $matches['token']));
                $arrayResponse = json_decode($jsonResponse, true);
                $videoUrl = $arrayResponse['gfyItem']['mp4Url'];
                $newContent = preg_replace('#<a href="(?P<href>[^"]*)">\[link\]</a>#', "<a href=\"${videoUrl}\">[link]</a>", $entry->content());
            } catch (Exception $e) {
                $newContent = sprintf('%s <p>GFYCAT ERROR</p>', $entry->content());
            }
            $entry->_content($newContent);
        }

        return $entry;
    }

    /**
     * @return bool
     */
    private function isRedditLink($entry) {
        return (bool) strpos($entry->link(), static::MATCH_REDDIT);
    }

    /**
     * @return string|null
     */
    private function getContentLink($entry) {
        if (preg_match('#<a href="(?P<href>[^"]*)">\[link\]</a>#', $entry->content(), $matches)) {
            return $matches['href'];
        }
    }

    private function addImageContent($entry, $href) {
        $entry->_content(sprintf(static::IMAGE_CONTENT,$href, $entry->link()));
    }

    private function addVideoContent($entry, $href, $extension) {
        $entry->_content(sprintf(static::VIDEO_CONTENT, $href, $extension, $entry->link()));
    }

    private function addLinkContent($entry, $href) {
        $entry->_content(sprintf(static::LINK_CONTENT, $entry->content(), $href));
    }
}
