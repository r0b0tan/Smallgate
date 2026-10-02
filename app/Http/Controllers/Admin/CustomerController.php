<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        return view('admin.customers.index', [
            'customers' => Customer::query()
                ->withCount(['projects', 'users'])
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        $customer = new Customer;
        $customer->is_active = true;

        return view('admin.customers.create', ['customer' => $customer]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = new Customer;
        $customer->fill($request->safe()->except('is_active'));
        // Not fillable: decides whether the customer's users can sign in.
        $customer->is_active = $request->boolean('is_active');
        $customer->save();

        Activity::record(ActivityAction::CustomerCreated, $customer);

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'Kunde wurde angelegt.');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        return view('admin.customers.show', [
            'customer' => $customer->load(['projects' => fn ($q) => $q->orderBy('name')]),
            'users' => $customer->users()->orderBy('name')->get(),
            'invitations' => $customer->invitations()->latest()->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('admin.customers.edit', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->fill($request->safe()->except('is_active'));
        $customer->is_active = $request->boolean('is_active');
        $customer->save();

        if ($customer->wasChanged('is_active')) {
            Activity::record($customer->is_active ? ActivityAction::CustomerActivated : ActivityAction::CustomerDeactivated, $customer);
        } elseif ($customer->wasChanged()) {
            Activity::record(ActivityAction::CustomerUpdated, $customer);
        }

        return redirect()->route('admin.customers.show', $customer)
            ->with('status', 'Kunde wurde gespeichert.');
    }
}
