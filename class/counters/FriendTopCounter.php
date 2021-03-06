<?php

namespace Achiev;

class FriendTopCounter extends FriendCountCounter  {

	public function isStageReversed () {
		return true;
	}

	public function hasRealThreshold () {
		return true;
	}

	public function getRealThreshold ( $pos = 0 ) {
		return $this->getPositionCount( 1 );
	}

	public function isQualify ( $count, $threshold = 1 ) {
		return $count > 0 && $count === $this->getPositionCount( $threshold ) && $count !== $this->getPositionCount( $threshold + 1 );
	}

	public function defaultConfig () {
		return [
			'init' => 0,
		];
	}
}