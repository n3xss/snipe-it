<?php

namespace App\Http\Controllers\Consumables;

use App\Events\CheckoutableCheckedOut;
use App\Helpers\Helper;
use App\Http\Controllers\CheckInOutRequest;
use App\Http\Controllers\Controller;
use App\Models\Consumable;
use App\Models\ConsumableAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use \Illuminate\Contracts\View\View;
use \Illuminate\Http\RedirectResponse;

class ConsumableCheckoutController extends Controller
{
    use CheckInOutRequest;

    /**
     * Return a view to checkout a consumable to a user or asset.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see ConsumableCheckoutController::store() method that stores the data.
     * @since [v1.0]
     * @param int $id
     */
    public function create($id) : View | RedirectResponse
    {

        if ($consumable = Consumable::find($id)) {

            $this->authorize('checkout', $consumable);

            // Make sure the category is valid
            if ($consumable->category) {

                // Make sure there is at least one available to checkout
                if ($consumable->numRemaining() <= 0){
                    return redirect()->route('consumables.index')
                        ->with('error', trans('admin/consumables/message.checkout.unavailable', ['requested' => 1, 'remaining' => $consumable->numRemaining()]));
                }

                // Return the checkout view
                return view('consumables/checkout', compact('consumable'));
            }

            // Invalid category
            return redirect()->route('consumables.edit', ['consumable' => $consumable->id])
                ->with('error', trans('general.invalid_item_category_single', ['type' => trans('general.consumable')]));
        }

        // Not found
        return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.does_not_exist'));

    }

    /**
     * Saves the checkout information
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @see ConsumableCheckoutController::create() method that returns the form.
     * @since [v1.0]
     * @param int $consumableId
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request, $consumableId)
    {
        if (is_null($consumable = Consumable::find($consumableId))) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.not_found'));
        }

        $this->authorize('checkout', $consumable);

        // If the quantity is not present in the request or is not a positive integer, set it to 1
        $quantity = $request->input('checkout_qty');
        if (!isset($quantity) || !ctype_digit((string)$quantity) || $quantity <= 0) {
            $quantity = 1;
        }

        // Make sure there is at least one available to checkout
        if ($consumable->numRemaining() <= 0 || $quantity > $consumable->numRemaining()) {
            return redirect()->route('consumables.index')->with('error', trans('admin/consumables/message.checkout.unavailable', ['requested' => $quantity, 'remaining' => $consumable->numRemaining() ]));
        }

        // Backward compatibility: if only assigned_to is provided (no checkout_to_type),
        // treat it as a user checkout
        if ($request->filled('assigned_to') && !$request->filled('checkout_to_type')) {
            $request->merge([
                'checkout_to_type' => 'user',
                'assigned_user' => $request->input('assigned_to'),
            ]);
        }

        try {
            $target = $this->determineCheckoutTarget();
        } catch (\Exception $e) {
            return redirect()->route('consumables.checkout.show', $consumable)->with('error', trans('admin/consumables/message.checkout.user_does_not_exist'))->withInput();
        }

        session()->put(['checkout_to_type' => $target]);

        for ($i = 0; $i < $quantity; $i++) {
            $consumable_assignment = new ConsumableAssignment([
                'consumable_id' => $consumable->id,
                'assigned_to' => $target->id,
                'assigned_type' => $target::class,
                'note' => $request->input('note'),
            ]);

            $consumable_assignment->created_by = auth()->id();
            $consumable_assignment->save();
        }

        $consumable->checkout_qty = $quantity;

        event(new CheckoutableCheckedOut(
            $consumable,
            $target,
            auth()->user(),
            $request->input('note'),
            [],
            $consumable->checkout_qty,
        ));

        $request->request->add(['checkout_to_type' => request('checkout_to_type')]);
        $request->request->add(['assigned_to' => $target->id]);

        session()->put(['redirect_option' => $request->input('redirect_option'), 'checkout_to_type' => $request->input('checkout_to_type')]);


        // Redirect to the new consumable page
        return Helper::getRedirectOption($request, $consumable->id, 'Consumables')
            ->with('success', trans('admin/consumables/message.checkout.success'));
    }
}
