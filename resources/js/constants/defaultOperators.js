import { usePage } from '@inertiajs/vue3';

// The operators an HIPL user's Operator filter opens with, besides their own.
// The list is OperatorScope::DEFAULT_FILTER_CODES on the server, shared as the
// `defaultOperatorCodes` Inertia prop, so adding an operator to the default is
// a one-line server change — never list codes in a page.
//
// Everyone else opens on their own operator only, so this returns [] for them.
// A code with no matching option (a deactivated operator) is skipped.
export function hiplDefaultOperators(authOperator, operatorOptions) {
  if (authOperator?.code !== 'HIPL') {
    return [];
  }

  const codes = usePage().props.defaultOperatorCodes ?? [];

  return codes
    .filter(code => code !== authOperator.code)
    .map(code => (operatorOptions ?? []).find(operator => operator?.code === code))
    .filter(Boolean);
}
