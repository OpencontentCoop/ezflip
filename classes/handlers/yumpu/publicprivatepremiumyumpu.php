<?php

class PublicPrivatePremiumFlipYumpu extends FlipYumpu
{
    function versions( $version = null )
    {
        $urlData = parse_url( eZINI::instance()->variable( 'SiteSettings', 'SiteURL' ) );
        
        if ( !$urlData )
        {
            throw new Exception( "Problem parsing 'SiteSettings/SiteUrl' " . eZINI::instance()->variable( 'SiteSettings', 'SiteURL' ) );
        }
        $domains = $urlData['path'];
        
        $versions = array(
            'public' => array(
                '_blurred' => true, 
                //'page_teaser_image' => null,
                //'page_teaser_page_range' => null,
                //'page_teaser_url' => null,
                'detect_elements' => 'n',
                'downloadable' => 'n', 
                'visibility' => 'public',                
                'recommended_magazines' => 'n',  
                'social_sharing' => 'n',  
                'player_social_sharing' => 'n',  
                'player_download_pdf' => 'n', 
                'player_print_page' => 'n',  
                'player_branding' => 'n',  
                'player_sidebar' => 'n',  
                'player_html5_c2r' => 'n',  
                'player_outer_shadow' => 'y',  
                'player_inner_shadow' => 'y',  
                //'player_ga' => ''   
            ),
            'private' => array(
                //'page_teaser_image' => null,
                //'page_teaser_page_range' => null,
                //'page_teaser_url' => null,
                'detect_elements' => 'y',
                'downloadable' => 'n', 
                'visibility' => 'dprotected',
                'domains' => $domains,
                'recommended_magazines' => 'n',  
                'social_sharing' => 'n',  
                'player_social_sharing' => 'n',  
                'player_download_pdf' => 'n', 
                'player_print_page' => 'n',  
                'player_branding' => 'n',  
                'player_sidebar' => 'n',  
                'player_html5_c2r' => 'y',  
                'player_outer_shadow' => 'y',  
                'player_inner_shadow' => 'y',  
                //'player_ga' => ''
            )
        );
        if ( $version !== null )
        {
            return isset( $versions[$version] ) ? $versions[$version] : null;
        }
        return $versions;
    }
    
    function getFlipData()
    {
        if ( isset( $this->flipList[$this->attribute->attribute( 'id' )] ) )
        {
            $data = $this->flipList[$this->attribute->attribute( 'id' )];
            
            // compat with simple yumpu handler fliplist
            if ( isset( $data[0]['document'] ) )
            {
                $data = array( 'public' => $data, 'private' => $data );
            }
            
            $data['can_read'] = $this->object->attribute( 'can_read' );
            $data['versions'] = array();
            foreach( array_keys( $this->versions() ) as $yumpuVersion )
            {
                if ( isset( $data[$yumpuVersion] ) )
                {                                        
                    if ( isset( $data[$yumpuVersion]['document'] ) )
                    {
                        $data['versions'][] = $yumpuVersion;
                        foreach( $data[$yumpuVersion]['document'] as $i => $doc )
                        {
                            //$id = $data['document'][0]['id'];
                            //$connector = new YumpuConnector();
                            //$connector->config['token'] = $this->FlipINI->variable( 'YumpuSettings', 'Token' );
                            //$connector->config['debug'] = true;
                            //$response = $connector->getDocument( array( 'id' => $id ) );
                            
                            if ( isset( $doc['embed_code'] ) )
                            {
                                $xml = new SimpleXMLElement( $doc['embed_code'] );
                                foreach( $xml->attributes() as $key => $value )
                                {
                                    if ( $key == 'src' )
                                    {
                                        $data[$yumpuVersion]['document'][$i]['iframe_src'] = (string) $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }        
            return $data;
        }
        else
        {
            return false;
        }
    }
    
    function convert()
    {
        if ( isset( $this->flipList[$this->attribute->attribute( 'id' )] ) )
        {
            $infos = array();
            $data = $this->flipList[$this->attribute->attribute( 'id' )];
            foreach( array_keys( $this->versions() ) as $yumpuVersion )
            {
                if ( isset( $data[$yumpuVersion] ) )
                {
                    $dataVersion = $data[$yumpuVersion];
                    if ( is_string( $dataVersion ) )
                    {
                        $response = $this->getID( $dataVersion );
                        if ( $response )
                        {
                            $this->flipList[$this->attribute->attribute( 'id' )][$yumpuVersion] = $response;
                            $this->updateFlipList();
                            eZContentCacheManager::clearObjectViewCache( $this->attribute->attribute( 'contentobject_id' ) );
                        }
                        else
                        {
                            $infos[] = "[$yumpuVersion] Conversion in progress";
                        }
                    }
                    else
                    {                        
                        $infos[] = "[$yumpuVersion] " . $this->updateFile( $yumpuVersion );
                    }
                }
                else
                {
                    $infos[] = $this->pushData( $yumpuVersion, $this->buildFileData( $yumpuVersion ) );
                }
            }
            if ( count( $infos ) > 0 )
            {
                throw new RuntimeException( implode( " ", $infos ) );
            }
        }
        else
        {
            $infoString = '';
            foreach( array_keys( $this->versions() ) as $yumpuVersion )
            {             
                $info = $this->pushData( $yumpuVersion, $this->buildFileData( $yumpuVersion ) );
                $infoString .= "[$yumpuVersion] $info \n"; 
            }
            throw new RuntimeException( $infoString );
        }
    }

    protected function getID( $progressId )
    {
        $connector = new YumpuConnector();
        $connector->config['token'] = $this->FlipINI->variable( 'YumpuSettings', 'Token' );
        $connector->config['debug'] = $this->FlipINI->variable( 'YumpuSettings', 'EnableDebug' );
        $response = $connector->getDocumentProgress( $progressId );
        if ( $response['state'] == 'success' )
        {
            return $response;
        }
        return false;
    }

    protected function updateFile( $yumpuVersion )
    {    
        if ( isset( $this->flipList[$this->attribute->attribute( 'id' )][$yumpuVersion]  ) )
        {
            $id = $this->flipList[$this->attribute->attribute( 'id' )][$yumpuVersion]['document'][0]['id'];
            $connector = new YumpuConnector();
            $connector->config['token'] = $this->FlipINI->variable( 'YumpuSettings', 'Token' );
            $connector->config['debug'] = $this->FlipINI->variable( 'YumpuSettings', 'EnableDebug' );
            $response = $connector->deleteDocument( $id );
            if ( $response['state'] != 'success' )
            {
                throw new Exception( "Error deleting $id" );
            }
        }

        return $this->pushData( $yumpuVersion, $this->buildFileData( $yumpuVersion ) );
    }
    
    protected function pushData( $yumpuVersion, $data )
    {
        $info = false;        
        $connector = new YumpuConnector();
        $connector->config['token'] = $this->FlipINI->variable( 'YumpuSettings', 'Token' );
        $connector->config['debug'] = $this->FlipINI->variable( 'YumpuSettings', 'EnableDebug' );
        $response = $connector->postDocumentFile( $data );
        if ( $response )
        {
            if ( isset( $response['progress_id'] ) )
            {
                $this->flipList[$this->attribute->attribute( 'id' )][$yumpuVersion] = $response['progress_id'];
                $this->updateFlipList();
                $info = "Waiting for remote conversion";
            }
            else
            {
                $info = "Field 'progress_id' not found in yumpu response";
            }
        }
        else
        {
            $info =  "Conversion failed" ;
        }
        return $info;
    }

    protected function buildFileData( $yumpuVersion )
    {        
        if ( $this->versions( $yumpuVersion ) !== null )
        {
            $storedFile = $this->attribute->storedFileInformation( false, false, false );
            $filePath = eZSys::rootDir() . '/' . $storedFile['filepath'];
    
            $title = $this->object->attribute( 'name' );
            $currentLanguageParts = explode( '-', eZLocale::instance( $this->object->attribute( 'current_language' ) )->attribute( 'http_locale_code' ) );
            $language = strtolower( $currentLanguageParts[0] ); //@todo trasformare in format iso
            if ( !$language )
            {
                throw new Exception( "Language not found" );
            }
    
            $dataMap = $this->object->attribute( 'data_map' );
            $description = $tags = $blurredPages = false;
            if ( $this->FlipINI->hasVariable( 'FlipBookSettings', 'DescriptionAttributeIdentifier' ) && isset( $dataMap[$this->FlipINI->variable( 'FlipBookSettings', 'DescriptionAttributeIdentifier' )] ))
            {
                $attribute = $dataMap[$this->FlipINI->variable( 'FlipBookSettings', 'DescriptionAttributeIdentifier' )];
                if ( $attribute->attribute( 'has_content' ) )
                {
                    $description = strip_tags( $attribute->toString() );
                }
            }
            if ( $this->FlipINI->hasVariable( 'FlipBookSettings', 'TagAttributeIdentifier' ) && isset( $dataMap[$this->FlipINI->variable( 'FlipBookSettings', 'TagAttributeIdentifier' )] ))
            {
                $attribute = $dataMap[$this->FlipINI->variable( 'FlipBookSettings', 'TagAttributeIdentifier' )];
                if ( $attribute->attribute( 'has_content' ) )
                {
                    $tags = strip_tags( $attribute->toString() );
                }
            }
            
            $blurredPages = '1-'; //all pages
            if ( $this->FlipINI->hasVariable( 'FlipBookSettings', 'BlurpagesAttributeIdentifier' ) && isset( $dataMap[$this->FlipINI->variable( 'FlipBookSettings', 'BlurpagesAttributeIdentifier' )] ))
            {
                $attribute = $dataMap[$this->FlipINI->variable( 'FlipBookSettings', 'BlurpagesAttributeIdentifier' )];
                if ( $attribute->attribute( 'has_content' ) )
                {
                    $blurredPages = $attribute->toString();
                }
            }
            
            $data = array(
                'file' => $filePath,
                'title' => $title,                
                'language' => $language,                
            );
            
            if ( $description ) $data['description'] = $description;            
            if ( $tags ) $data['tags'] = $tags;
            
            $data = array_merge( $data, $this->versions( $yumpuVersion ) );
            
            if ( isset( $data['_blurred'] ) )
            {
                $doBlur = $data['_blurred'];
                unset( $data['_blurred'] );
                if ( $doBlur )
                    $data['blurred'] =  $blurredPages;
            }
            
            return $data;
        }
        
        throw new Exception( "Version $yumpuVersion not defined" );
    }

    /**
     * @return string
     */
    function template()
    {
        return 'design:ezflip/public_private_yumpu.tpl';
    }
}
