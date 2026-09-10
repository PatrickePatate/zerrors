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
        </div>
    @empty
        <p class="text-sm text-gray-400">No notification channels configured yet.</p>
    @endforelse

    <div class="rounded-md border border-dashed border-gray-300 p-3">
        <h3 class="text-xs font-medium tracking-wide text-gray-600 uppercase">Add channel</h3>

        <div class="mt-2 w-48">
            <x-form.select wire:model.live="newChannelType" id="newChannelType"
                            :options="['slack' => 'Slack', 'telegram' => 'Telegram', 'email' => 'Email']"
                            :selected="$newChannelType" />
        </div>

        <div class="mt-3 space-y-3">
            @if($newChannelType === 'slack')
                <x-form.text-input label="Slack webhook URL" wire:model="newWebhookUrl"
                                    name="newWebhookUrl" placeholder="https://hooks.slack.com/services/…"
                                    :error="$errors->first('newWebhookUrl')" />
            @elseif($newChannelType === 'telegram')
                <div class="grid grid-cols-2 gap-3">
                    <x-form.text-input label="Bot token" wire:model="newBotToken" name="newBotToken"
                                        placeholder="123456:ABC-…" :error="$errors->first('newBotToken')" />
                    <x-form.text-input label="Chat ID" wire:model="newChatId" name="newChatId"
                                        placeholder="-100123456789" :error="$errors->first('newChatId')" />
                </div>
                <p class="text-xs text-gray-400">Message @BotFather to create a bot, then add it to a chat/channel to find the chat ID.</p>
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
