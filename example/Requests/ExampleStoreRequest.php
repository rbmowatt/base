<?php

namespace Example\Requests;

use RBMowatt\Base\Requests\BaseFormRequest;

/**
* Where validation lives: in the request, not in the Controller and not in the
* Service.
*
* Laravel resolves this while injecting it into the action, so failedValidation()
* fires BEFORE the controller body runs. The try/catch in the controller cannot
* see it — the host app's exception handler is what renders it. See the README
* section on wiring the handler.
*/
class ExampleStoreRequest extends BaseFormRequest
{
    /**
    * Coarse gate. Returning false here reaches failedAuthorization(), which throws
    * rather than letting the request continue.
    */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
    * @return array<string, mixed>
    */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'widget_type_id' => ['required', 'integer', 'exists:widget_types,id'],
            'account_type' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
    * Runs after the rules pass, so anything needing the validated values or a
    * loaded record belongs here rather than in authorize().
    *
    * @param \Illuminate\Contracts\Validation\Validator $validator
    * @return void
    */
    protected function checkPermissions($validator)
    {
        if ($this->input('account_type') === 'internal' && !$this->user()->is_admin) {
            $this->throwPermissionsException('Only an admin can create an internal account.');
        }
    }
}
