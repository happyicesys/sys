@extends('mail')

@section('content')
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 720px; margin: 0 auto; font-family: Arial, sans-serif; color: #1a202c;">
    <tr>
        <td style="padding: 24px;">
            <h2 style="margin: 0 0 12px; font-size: 20px; font-weight: 600; color: #0f172a;">
                Card settlement — needs a person
            </h2>
            <p style="margin: 0 0 16px; font-size: 14px; color: #475569;">
                Generated at {{ $generatedAt->format('Y-m-d H:i') }}. Everything else was matched, repaired and synced automatically.
            </p>

            @foreach ($sections as $section)
                <h3 style="margin: 20px 0 4px; font-size: 16px; font-weight: 600; color: #0f172a;">
                    {{ $section['title'] }} ({{ count($section['items']) }})
                </h3>
                <p style="margin: 0 0 8px; font-size: 13px; color: #475569;">{{ $section['action'] }}</p>
                <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; width: 100%; border: 1px solid #e2e8f0; font-size: 14px;">
                    @foreach ($section['items'] as $index => $item)
                        <tr style="{{ $index % 2 === 0 ? 'background-color: #ffffff;' : 'background-color: #f8fafc;' }}">
                            <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">{{ $item['text'] }}</td>
                            <td align="right" style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; white-space: nowrap;">
                                @if (! empty($item['url']))
                                    <a href="{{ $item['url'] }}" style="color: #2563eb;">Open</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endforeach
        </td>
    </tr>
</table>
@endsection
