<div class="space-y-4">
    @forelse($channels as $channel)
        <div class="rounded-md border border-gray-200 p-3">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <x-badge :color="$channel->enabled ? 'green' : 'gray'">{{ $channel->type->label() }}</x-badge>
                    <span class="text-sm text-gray-700">{{ $channel->label() }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="toggleChannel({{ $channel->id }})" class="text-xs font-medium text-gray-600 hover:underline">
                        {{ $channel->enabled ? 'Disable' : 'Enable' }}
                    </button>
                    <button type="button" wire:click="deleteChannel({{ $channel->id }})"
                            wire:confirm="Remove this notification channel?"
                            class="text-xs font-medium text-red-600 hover:underline">
                        Remove
                    </button>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 border-t border-gray-100 pt-3">
                @foreach($triggers as $trigger)
                    @php($rule = $channel->rules->firstWhere('trigger', $trigger))
                    <x-form.checkbox wire:click="toggleRule({{ $channel->id }}, '{{ $trigger->value }}')" :checked="(bool) $rule?->enabled">
                        {{ $trigger->label() }}
                    </x-form.checkbox>
                @endforeach
            </div>

            <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
                <label class="text-sm text-gray-700" for="thresholds-{{ $channel->id }}">Occurrence thresholds</label>
                <div class="w-64">
                    <x-form.text-input id="thresholds-{{ $channel->id }}" name="thresholds-{{ $channel->id }}"
                                        wire:model="thresholdInputs.{{ $channel->id }}"
                                        wire:change="updateThresholds({{ $channel->id }})"
                                        placeholder="1, 10, 100, 1000" />
                </div>
                <span class="text-xs text-gray-400">Notify when the issue is seen exactly this many times.</span>
            </div>

            @if($channel->rules->firstWhere('trigger', \App\Enums\NotificationRuleTrigger::Spike)?->enabled)
                <div class="mt-3 grid grid-cols-3 gap-3 border-t border-gray-100 pt-3">
                    <x-form.text-input label="Window (minutes)" type="number" id="spike-window-{{ $channel->id }}" name="spike-window-{{ $channel->id }}"
                                        wire:model="spikeInputs.{{ $channel->id }}.window_minutes"
                                        wire:change="updateSpikeSettings({{ $channel->id }})" />
                    <x-form.text-input label="Times the 24h average" type="number" id="spike-multiplier-{{ $channel->id }}" name="spike-multiplier-{{ $channel->id }}"
                                        wire:model="spikeInputs.{{ $channel->id }}.multiplier"
                                        wire:change="updateSpikeSettings({{ $channel->id }})" />
                    <x-form.text-input label="Minimum events" type="number" id="spike-min-{{ $channel->id }}" name="spike-min-{{ $channel->id }}"
                                        wire:model="spikeInputs.{{ $channel->id }}.min_count"
                                        wire:change="updateSpikeSettings({{ $channel->id }})" />
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400">No notification channels configured yet.</p>
    @endforelse

    <div class="rounded-md border border-dashed border-gray-300 p-3">
        <h3 class="text-xs font-medium tracking-wide text-gray-600 uppercase">Add channel</h3>

        <div class="mt-2 w-48">
            <x-form.select wire:model.live="newChannelType" id="newChannelType"
                            :options="['slack' => 'Slack', 'telegram' => 'Telegram', 'email' => 'Email', 'discord' => 'Discord', 'webhook' => 'Webhook']"
                            :selected="$newChannelType" />
        </div>

        <div class="mt-3 space-y-3">
            @if($newChannelType === 'slack')
                @if($organization->slack_bot_token)
                    <x-form.select label="Slack channel" wire:model="newSlackChannelId" id="newSlackChannelId"
                                    :options="collect($slackChannels)->mapWithKeys(fn ($channel) => [$channel['id'] => '#'.$channel['name']])"
                                    :selected="$newSlackChannelId" :error="$errors->first('newSlackChannelId')" />
                @else
                    <p class="text-sm text-gray-500">
                        Connect Slack to this organization before adding a Slack channel.
                        <a href="{{ route('integrations.slack.redirect', $organization) }}" class="font-medium text-gray-900 underline">Connect Slack</a>
                    </p>
                @endif
            @elseif($newChannelType === 'telegram')
                <div class="grid grid-cols-2 gap-3">
                    <x-form.text-input label="Bot token" wire:model="newBotToken" name="newBotToken"
                                        placeholder="123456:ABC-…" :error="$errors->first('newBotToken')" />
                    <x-form.text-input label="Chat ID" wire:model="newChatId" name="newChatId"
                                        placeholder="-100123456789" :error="$errors->first('newChatId')" />
                </div>
                <p class="text-xs text-gray-400">Message @BotFather to create a bot, then add it to a chat/channel to find the chat ID.</p>
            @elseif($newChannelType === 'discord')
                <x-form.text-input label="Discord webhook URL" wire:model="newDiscordWebhookUrl" name="newDiscordWebhookUrl"
                                    placeholder="https://discord.com/api/webhooks/…" :error="$errors->first('newDiscordWebhookUrl')" />
            @elseif($newChannelType === 'webhook')
                <div class="grid grid-cols-2 gap-3">
                    <x-form.text-input label="Webhook URL" wire:model="newWebhookUrl" name="newWebhookUrl"
                                        placeholder="https://example.com/hooks/zerrors" :error="$errors->first('newWebhookUrl')" />
                    <x-form.text-input label="Signing secret (optional)" wire:model="newWebhookSecret" name="newWebhookSecret"
                                        :error="$errors->first('newWebhookSecret')" />
                </div>
                <p class="text-xs text-gray-400">A JSON body is POSTed; with a secret it is signed in the X-Zerrors-Signature header (sha256 HMAC).</p>
            @elseif($newChannelType === 'email')
                <x-form.text-input label="Alert email" type="email" wire:model="newEmail" name="newEmail"
                                    placeholder="alerts@example.com" :error="$errors->first('newEmail')" />
            @endif
        </div>

        <div class="mt-3 flex justify-end">
            <x-button type="button" wire:click="addChannel">Add channel</x-button>
        </div>
    </div>
</div>
