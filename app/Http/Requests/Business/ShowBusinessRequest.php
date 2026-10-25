<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ShowBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the profile identifier.
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
            'review' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Read the slug from the route rather than a query parameter.
     */
    public function validationData(): array
    {
        return [
            ...$this->query(),
            'slug' => $this->route('slug'),
        ];
    }

    /**
     * Identify full profile visits while excluding fetches and recognizable crawlers.
     */
    public function profileVisitorKey(): ?string
    {
        $agent = trim($this->userAgent() ?? '');
        $purpose = $this->header('Purpose').' '.$this->header('Sec-Purpose').' '.$this->header('X-Purpose');

        if (! $this->isMethod('GET') || $agent === ''
            || $this->headers->has('X-Inertia-Partial-Component')
            || $this->headers->has('X-Inertia-Partial-Data')
            || $this->headers->has('X-Inertia-Partial-Except')
            || $this->headers->has('X-Inertia-Prefetch')
            || preg_match('/prefetch|prerender/i', $purpose)
            || preg_match('/bot|crawler|spider|slurp|preview|headless|lighthouse|facebookexternalhit|whatsapp|curl|wget|python-requests/i', $agent)) {
            return null;
        }

        if ($this->user()) {
            return 'account:'.$this->user()->id;
        }

        $token = $this->session()->get('profile_view_visitor');

        if (! is_string($token) || strlen($token) !== 36) {
            $token = (string) Str::uuid();
            $this->session()->put('profile_view_visitor', $token);
        }

        return 'session:'.$token;
    }
}
