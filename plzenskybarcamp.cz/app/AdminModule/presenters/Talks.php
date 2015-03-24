<?php

namespace App\AdminModule\Presenters;

use Nette,
	App\Model,
    Nette\Application\UI\Form,
    Nette\Templating\FileTemplate,
    App\Components\BootstrapizeForm;


  class TalksPresenter extends BasePresenter
{

    private $registrationModel;

    public function __construct( Model\Registration $registrationModel ) {
        $this->registrationModel = $registrationModel;
    }

    public function renderList( ) {
        $this->template->talks = $this->registrationModel->getTalks();
    }

    public function actionCsv( ) {
        $talks = $this->registrationModel->getTalks();;

        ob_start();
        $df = fopen("php://output", 'w');
        fputcsv($df, array("ID", "Název", "Speaker", "E-mail", "Popis", "Pro koho je určena"), ",", '"');
        foreach ($talks as $talk) {
            @fputcsv($df, array(
                $talk['_id'],
                $talk['title'],
                $talk['speaker']['name'],
                $talk['speaker']['email'],
                $talk['description'],
                $talk['purpose'],
            ), ",", '"');
        }
        fclose($df);
        $csv = ob_get_clean();

        $now = gmdate("D, d M Y H:i:s");
        $fileDatePostfix = gmdate("Ymd.his");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");

        // force download
        header("Content-Type: application/force-download", TRUE);
        header("Content-Type: application/octet-stream", FALSE);
        header("Content-Type: application/download", FALSE);
        header("Content-Length: " . strlen($csv));

        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename=talks-$fileDatePostfix.csv");
        header("Content-Transfer-Encoding: binary");
        echo $csv;
        $this->terminate();
    }

    public function renderDetail( $talkId ) {
        $talk = $this->registrationModel->findTalk( $talkId );

        if ( ! $talk ) {
            throw new Nette\Application\BadRequestException( 'Talks not found', '404');
        }

        $this->template->talk = $talk;
        $this->template->talkId = $talkId;
    }

    private function createLinkEditTemplate() {
        $this->getTemplate()->setFile( __DIR__ . '/../templates/Talks/editLink.latte' );
    }

    public function renderAddLink( $type, $talkId ) {
        $this->createLinkEditTemplate();

        $this[ 'linkEditForm' ]->setDefaults( array(
            'type' => $type,
            'talkId' => $talkId
        ));

        $this->template->pageTitle = "Přidat odkaz k přednášce";
    }

    public function renderEditLink( $type, $talkId, $linkId ) {
        $this->createLinkEditTemplate();

        $talk = $this->registrationModel->findTalk( $talkId );

        if ( ! isset($talk[ $type ][ $linkId ] ) ) {
            throw new Nette\Application\BadRequestException( 'Link not found', '404');
        }

        $link = $talk[ $type ][ $linkId ];


        $this[ 'linkEditForm' ]->setDefaults( $link + array(
            'type' => $type,
            'talkId' => $talkId,
            'linkId' => $linkId
        ));

        $this->template->pageTitle = "Upravit odkaz k přednášce";
    }

    public function renderRemoveLink( $type, $talkId, $linkId ) {
        $talk = $this->registrationModel->findTalk( $talkId );

        if ( ! isset($talk[ $type ][ $linkId ] ) ) {
            throw new Nette\Application\BadRequestException( 'Link not found', '404');
        }

        $this[ 'linkRemoveForm' ]->setDefaults( array(
            'type' => $type,
            'talkId' => $talkId,
            'linkId' => $linkId
        ));
    }

    public function createComponentLinkEditForm() {
        $form = new Form();

        $form->addHidden( 'type' );
        $form->addHidden( 'talkId' );
        $form->addHidden( 'linkId' );
        $form->addCheckbox( 'is_public', 'Publikováno')
            ->setDefaultValue( TRUE );
        $form->addText( 'title', 'Nadpis', 40)
            ->setRequired('Must be valid title.');
        $form->addText( 'url', 'URL', 40)
            ->addRule(Form::URL, 'Must be a valid URL')
            ->setRequired('Must be valid URL.');
        $form->addSubmit( 'send', 'Uložit');

        $form->onSuccess[] = array( $this, 'processLinkEditForm');
        BootstrapizeForm::bootstrapize( $form );
        return $form;
    }

    public function processLinkEditForm( $form ) {
        $values = $form->getValues( TRUE );

        $type = $values[ 'type' ];
        $talkId = $values[ 'talkId' ];
        $linkId = $values[ 'linkId' ];
        unset(
            $values[ 'type' ],
            $values[ 'talkId' ],
            $values[ 'linkId' ]
        );

        if( ! $type ) {
            $form->addError( "Interní chyba: Neplatný typ kategorie linku" );
        }
        if( ! $talkId ) {
            $form->addError( "Interní chyba formuláře: Neplatné ID přednášky" );
        }

        if( $linkId ) {
            $this->registrationModel->editLinkToTalk( $talkId, $type, $linkId, $values );
            $this->flashMessage( 'Odkaz byl upraven' );
        }
        else {
            $this->registrationModel->addLinkToTalk( $talkId, $type, $values );
            $this->flashMessage( 'Odkaz byl přidán' );
        }

        $this->redirect('detail', array('talkId'=>$talkId) );
    }

    public function createComponentLinkRemoveForm() {
        $form = new Form();

        $form->addHidden( 'type' );
        $form->addHidden( 'talkId' );
        $form->addHidden( 'linkId' );
        $form->addSubmit( 'yes', 'Ano, opravdu smazat')
            ->getControlPrototype()->class[] = 'btn-danger';
        $form->addSubmit( 'cancel', 'Storno')
            ->getControlPrototype()->class[] = 'btn-info';

        $form->addProtection("Selhalo bezpečnostní ověření, pošlete formulář znovu", 120);

        $form->onSuccess[] = array( $this, 'processLinkRemoveForm');
        BootstrapizeForm::bootstrapize( $form );
        return $form;
    }

    public function processLinkRemoveForm( $form ) {
        $values = $form->getValues( TRUE );

        $type = $values[ 'type' ];
        $talkId = $values[ 'talkId' ];
        $linkId = $values[ 'linkId' ];

        if ( ! $form['yes']->isSubmittedBy()) {
            $this->flashMessage('Operace stornována', 'warning');
            $this->redirect( 'detail', array( 'talkId' => $talkId) );
        }


        if( ! $type ) {
            $form->addError( "Interní chyba: Neplatný typ kategorie linku" );
        }
        if( ! $talkId ) {
            $form->addError( "Interní chyba formuláře: Neplatné ID přednášky" );
        }
        if( ! $linkId ) {
            $form->addError( "Interní chyba formuláře: Neplatné ID linku" );
        }

        $this->registrationModel->removeLinkFromTalk( $talkId, $type, $linkId );
        $this->flashMessage( 'Odkaz byl smazán' );

        $this->redirect('detail', array('talkId'=>$talkId) );
    }

 
}

