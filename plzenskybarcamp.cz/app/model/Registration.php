<?php

namespace App\Model;

class Registration {

	private $confereeCollection;
	private $talkCollection;

	public function __construct( $host ) {
		$client = new \MongoClient( $host );
		$database = $client->barcamp;
		$this->confereeCollection = $database->conferee;
		$this->talkCollection = $database->talk;
	}

	public function updateConferree( $userId, array $data ) {
		$this->updateConferreeByCondition( array( 'user_id' => $userId ), $data );
		$conferee = $this->findCoferree( $userId );
		if ( isset( $conferee['talk'] ) ) {
			$this->syncSpeakerWithTalk( $conferee['talk']['talk_id'], $conferee );
		}
	}

	public function findCoferree( $userId ) {
		$data = $this->findCoferrees( array( 'user_id' => $userId ) );
		return $data->getNext();
	}

	public function createTalk( $userId, array $data ) {
		$data['talk_id'] = uniqid(); // may use talk ID as url key
		$data['created_date'] = new \MongoDate( time() );
		$speaker = $this->findCoferree( $userId );
		$this->talkCollection->insert( $data );
		$this->syncTalkWithSpeaker( $userId, $data );
		$this->syncSpeakerWithTalk( $data['talk_id'], $speaker );
	}

	public function updateTalk( $talkId, array $data ) {
		$this->updateTalkByCondition( array( 'talk_id' => $talkId ), $data );
		$talk = $this->findTalk( $talkId );
		$this->syncTalkWithSpeaker( $talk['speaker']['user_id'], $talk );
	}

	public function findTalk( $talkId ) {
		return $this->talkCollection->find( array( 'talk_id' => $talkId ) )->getNext();
	}

	public function getTalks() {
		return $this->talkCollection->find()->sort( array('created_date' => -1 ) );
	}

	public function getSpeakers() {
		return $this->findCoferrees( array( 'talk' => array( '$ne' => null ) ) )->sort( array('created_date' => -1) );
	}

	public function getConferrees() {
		return $this->findCoferrees()->sort( array('created_date' => -1) );
	}

	public function getVotesCount( $talkId ) {
		$talk = $this->findTalk( $talkId );
		return isset( $talk['votes_count'] ) ? $talk[ 'votes_count' ] : 0;
	}

	public function addVote( $talkId, $userId ) {
		$this->talkCollection->update(
			array( 'talk_id' => $talkId ),
			array(
				'$push' => array( 'votes' => $userId ),
				'$inc' => array( 'votes_count' => 1 )
			),
			array( 'upsert' => TRUE )
		);
	}

	public function removeVote( $talkId, $userId ) {
		$this->talkCollection->update(
			array( 'talk_id' => $talkId ),
			array(
				'$pull' => array( 'votes' => $userId ),
				'$inc' => array( 'votes_count' => -1 )
			),
			array( 'upsert' => TRUE )
		);
	}

	public function hasTalk( $talkId ) {
		return $this->talkCollection->find( array( 'talk_id' => $talkId ) )->hasNext();
	}

	private function findCoferrees( $condition = array() ) {
		return $this->confereeCollection->find( $condition );
	}

	private function updateConferreeByCondition( $condition, array $data ) {
		return $this->confereeCollection->update( $condition,
			array( '$set' => $data ), array( 'upsert' => true ) );
	}

	private function updateTalkByCondition( $condition, array $data ) {
		return $this->talkCollection->update( $condition,
			array( '$set' => $data ), array( 'upsert' => true ) );
	}

	private function syncTalkWithSpeaker( $speakerId, array $data ) {
		unset( $data['speaker'] );
		unset( $data['_id'] );
		unset( $data['votes'] );
		unset( $data['votes_count'] );
		$this->updateConferreeByCondition( array( 'user_id' => $speakerId ), array( 'talk' => $data ) );
	}

	private function syncSpeakerWithTalk( $talkId, array $data ) {
		unset( $data['talk'] );
		unset( $data['_id'] );
		$this->updateTalkByCondition( array( 'talk_id' => $talkId ), array( 'speaker' => $data ) );
	}
}