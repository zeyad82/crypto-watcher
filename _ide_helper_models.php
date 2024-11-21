<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $crypto_id
 * @property int $volume_id
 * @property string $trend
 * @property string|null $previous_trend
 * @property string $entry
 * @property string $stop_loss
 * @property string $tp1
 * @property string $tp2
 * @property string $tp3
 * @property int|null $result
 * @property string|null $highest_price
 * @property string|null $lowest_price
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Crypto $crypto
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereCryptoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereEntry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereHighestPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereLowestPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert wherePreviousTrend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereStopLoss($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereTp1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereTp2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereTp3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereTrend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Alert whereVolumeId($value)
 */
	class Alert extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $symbol
 * @property string $base_asset
 * @property string $quote_asset
 * @property string|null $volume24
 * @property string|null $last_trend
 * @property \Illuminate\Support\Carbon|null $last_volume_alert
 * @property \Illuminate\Support\Carbon|null $last_fetched
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Alert> $alerts
 * @property-read int|null $alerts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\VolumeData> $volumeData
 * @property-read int|null $volume_data_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereBaseAsset($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereLastFetched($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereLastTrend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereLastVolumeAlert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereQuoteAsset($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crypto whereVolume24($value)
 */
	class Crypto extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $crypto_id
 * @property string|null $open
 * @property string|null $high
 * @property string|null $low
 * @property string|null $close
 * @property string|null $last_volume
 * @property string|null $latest_price
 * @property string|null $vma_15
 * @property string|null $vma_25
 * @property string|null $vma_50
 * @property string|null $price_ema_15
 * @property string|null $price_ema_25
 * @property string|null $price_ema_50
 * @property \Illuminate\Support\Collection|null $meta
 * @property string $timestamp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Crypto $crypto
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereClose($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereCryptoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereLastVolume($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereLatestPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereOpen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData wherePriceEma15($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData wherePriceEma25($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData wherePriceEma50($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereVma15($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereVma25($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolumeData whereVma50($value)
 */
	class VolumeData extends \Eloquent {}
}

