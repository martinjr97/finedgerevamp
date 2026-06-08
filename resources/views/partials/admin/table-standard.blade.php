{{--
    Standard Admin Table Component
    
    This component provides a consistent table structure for all admin pages.
    Use this as a reference when creating new admin tables.
    
    Usage Pattern:
    
    <div class="rounded-3xl border border-white/10 bg-white/5 p-4 shadow-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full w-full text-base text-slate-300">
                <thead>
                    <tr class="text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20">
                        <th class="px-4 py-4 text-lg border-r border-white/10">Column 1</th>
                        <th class="px-4 py-4 text-lg border-r border-white/10">Column 2</th>
                        <th class="px-4 py-4 text-lg">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr class="border-t border-white/40 text-center hover:bg-white/5 transition">
                            <td class="px-4 py-4 border-r border-white/5">
                                {{ $item->field }}
                            </td>
                            <td class="px-4 py-4 border-r border-white/5">
                                {{ $item->field2 }}
                            </td>
                            <td class="px-4 py-4">
                                <!-- Action buttons here -->
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    Key Design Standards:
    
    1. Table Classes:
       - Base: "min-w-full w-full text-base text-slate-300"
       - Headers: "text-base font-semibold uppercase tracking-[0.25em] text-white/80 text-center border-b-2 border-white/20"
       - Header Cells: "px-4 py-4 text-lg border-r border-white/10"
       - Rows: "border-t border-white/40 text-center hover:bg-white/5 transition"
       - Cells: "px-4 py-4 border-r border-white/5" (except last column)
       - Note: Zebra striping (alternating row colors) is automatically applied via CSS:
         * Dark mode: Odd rows have subtle white background, even rows slightly more visible
         * Light mode: Odd rows are white (#ffffff), even rows are light gray (#f8fafc)
    
    2. Status Display:
       - Use plain colored text instead of badges
       - Format: <span class="text-sm font-medium text-{color}-400">{{ status }}</span>
       - Colors: emerald (active/approved), amber (pending), rose (failed/inactive), blue (processing)
    
    3. Action Buttons:
       - View Button: blue-purple gradient with eye icon
       - Edit Button: purple-indigo gradient with edit icon
       - Format: inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-{color}-500/40 to-{color}-500/40 border-2 border-{color}-400/70 px-4 py-2 text-base font-semibold
       - Include SVG icons (w-5 h-5)
    
    4. Header Buttons:
       - All use: 'class' => 'bg-gradient-to-r from-blue-500 to-purple-600 shadow-blue-500/30 text-white'
    
    5. Font Sizes:
       - Table base: text-base (16px)
       - Headers: text-lg (18px)
       - Body text: text-base (16px)
       - Small text/details: text-sm (14px)
--}}

