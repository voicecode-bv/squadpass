@props(['user', 'size' => 'xs'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <flux:avatar :size="$size" :src="$user->avatarUrl()" :name="$user->name" :initials="$user->initials()" />
    <span>{{ $user->name }}</span>
</span>
