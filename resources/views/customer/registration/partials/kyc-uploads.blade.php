<div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
    <h2 class="text-lg font-semibold text-slate-900">KYC documents</h2>
    <p class="text-sm text-slate-600">Upload clear photos or scans of your identification. Optional but recommended.</p>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="document_type" class="block text-sm font-medium text-slate-800">Document type</label>
            <select id="document_type" name="document_type" class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
                <option value="">Select document type</option>
                <option value="nrc" @selected(old('document_type') === 'nrc')>NRC</option>
                <option value="passport" @selected(old('document_type') === 'passport')>Passport</option>
                <option value="drivers_license" @selected(old('document_type') === 'drivers_license')>Driver's License</option>
                <option value="voters_card" @selected(old('document_type') === 'voters_card')>Voter's Card</option>
                <option value="other" @selected(old('document_type') === 'other')>Other</option>
            </select>
            @error('document_type')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label for="front_image" class="block text-sm font-medium text-slate-800">Front of ID</label>
            <input id="front_image" name="front_image" type="file" accept="image/jpeg,image/png,image/jpg"
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900">
            <p class="mt-1 text-xs text-slate-500">JPEG or PNG, max 15MB.</p>
            @error('front_image')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="back_image" class="block text-sm font-medium text-slate-800">Back of ID (optional)</label>
            <input id="back_image" name="back_image" type="file" accept="image/jpeg,image/png,image/jpg"
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900">
            @error('back_image')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="profile_picture" class="block text-sm font-medium text-slate-800">Profile picture (optional)</label>
            <input id="profile_picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/jpg"
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900">
            @error('profile_picture')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="payslip" class="block text-sm font-medium text-slate-800">Payslip (optional)</label>
            <input id="payslip" name="payslip" type="file" accept=".pdf,image/jpeg,image/png,image/jpg"
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900">
            @error('payslip')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="bank_statement" class="block text-sm font-medium text-slate-800">Bank statement (optional)</label>
            <input id="bank_statement" name="bank_statement" type="file" accept=".pdf,image/jpeg,image/png,image/jpg"
                class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900">
            @error('bank_statement')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
