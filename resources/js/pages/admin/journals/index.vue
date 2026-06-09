<template>
    <AppLayout>

        <Head title="Journal Management" />
        <Toast />

        <div class="grid">
            <div class="col-12">
                <div class="card">
                    <!-- Toolbar -->
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h5 class="m-0 text-xl font-bold text-gray-800">
                                General Journal
                            </h5>
                            <p class="mt-1 text-gray-600">
                                Total: {{ journals.total || 0 }} journals
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button label="New Journal" icon="pi pi-plus" class="p-button-success" @click="openNew" />
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="mb-6 px-4">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center">
                            <div class="flex-1">
                                <div class="relative w-full">
                                    <i class="pi pi-search absolute top-1/2 left-3.5 -translate-y-1/2 text-gray-400" />
                                    <InputText v-model="searchQuery" @input="performSearch"
                                        placeholder="Search journals..." class="w-full pl-11" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button label="Clear" icon="pi pi-times" severity="secondary" @click="clearSearch"
                                    :disabled="!searchQuery" outlined />
                                <Button label="Search" icon="pi pi-search" @click="performSearch" severity="info" />
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="px-4">
                        <DataTable :value="tableData" paginator :rows="journals.per_page || 10"
                            :totalRecords="journals.total"
                            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} journals"
                            :rowsPerPageOptions="[10, 20, 50, 100, 200, 500]" :first="(journals.current_page - 1) * journals.per_page
                                " @page="onPageChange" @rows-change="onRowsPerPageChange" responsiveLayout="scroll"
                            dataKey="id" class="p-datatable-sm" stripedRows scrollable scrollHeight="flex">
                            <Column field="journal_number" header="Journal #" sortable :style="{ minWidth: '120px' }">
                                <template #body="slotProps">
                                    <Link :href="'/journals/' + slotProps.data.id"
                                        class="rounded px-1 py-0.5 font-semibold text-blue-600 transition-all hover:text-blue-800 hover:underline focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none">
                                        {{
                                            slotProps.data.journal_number ||
                                            'N/A'
                                        }}
                                    </Link>
                                </template>
                            </Column>

                            <Column field="journal_date" header="Journal Date" sortable :style="{ minWidth: '120px' }">
                                <template #body="slotProps">
                                    {{
                                        formatDate(slotProps.data.journal_date)
                                    }}
                                </template>
                            </Column>

                            <!-- <Column field="posting_date" header="Posting Date" sortable :style="{ minWidth: '120px' }">
                                <template #body="slotProps">
                                    {{
                                        formatDate(slotProps.data.posting_date)
                                    }}
                                </template>
                            </Column> -->

                            <Column field="description" header="Description" sortable :style="{ minWidth: '200px' }">
                                <template #body="slotProps">
                                    <div class="line-clamp-2">
                                        {{
                                            slotProps.data.description || 'N/A'
                                        }}
                                    </div>
                                </template>
                            </Column>

                            <Column field="total_amount" header="Amount" sortable :style="{ minWidth: '150px' }">
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <!-- <span class="font-bold">
                                            {{
                                                formatCurrency(
                                                    slotProps.data.total_debit,
                                                )
                                            }}
                                        </span> -->
                                        <span class="font-bold text-red-500">
                                            D:
                                            {{
                                                formatCurrency(
                                                    slotProps.data.total_debit,
                                                )
                                            }} </span>
                                        <span class="font-bold text-green-600">

                                            C:
                                            {{
                                                formatCurrency(
                                                    slotProps.data.total_credit,
                                                )
                                            }}

                                        </span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="mda" header="Admin Code / MDA" sortable :style="{ minWidth: '150px' }">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.mda">
                                        <Tag :value="slotProps.data.mda.code" severity="info" size="small"
                                            class="mr-2" />
                                        <span>
                                            {{ slotProps.data.mda.name }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400">
                                        N/A
                                    </span>
                                </template>
                            </Column>

                            <Column field="administrative_sector_code" header="Administrative Sector" sortable
                                :style="{ minWidth: '150px' }">
                                <template #body="slotProps">
                                    <div v-if="slotProps.data.administrative_sector_code">
                                        <Tag :value="slotProps.data.administrative_sector_code
                                            .code
                                            " severity="warning" size="small" class="mr-2" />
                                        <span>
                                            {{
                                                slotProps.data.administrative_sector_code
                                                    .name
                                            }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400">
                                        N/A
                                    </span>
                                </template>
                            </Column>

                            <Column field="status" header="Status" sortable :style="{ minWidth: '140px' }">
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1">
                                        <Tag :value="slotProps.data.status ||
                                            'Unknown'
                                            " :severity="getStatusSeverity(
                                                slotProps.data.status,
                                            )
                                                " />
                                        <small v-if="!slotProps.data.is_balanced" class="text-xs text-red-500">
                                            Not Balanced
                                        </small>
                                    </div>
                                </template>
                            </Column>

                            <Column field="entry_count" header="Entries" sortable :style="{ minWidth: '80px' }">
                                <template #body="slotProps">
                                    <Badge :value="slotProps.data.entry_count" severity="info" size="small" />
                                </template>
                            </Column>

                            <Column header="Actions" :exportable="false" :style="{ minWidth: '100px' }">
                                <template #body="slotProps">
                                    <div class="flex gap-1">
                                        <Button icon="pi pi-print"
                                            class="p-button-rounded p-button-secondary p-button-sm"
                                            v-tooltip="'Print Journal'" @click="
                                                printJournal(slotProps.data.id)
                                                " />
                                        <Button icon="pi pi-pencil"
                                            class="p-button-rounded p-button-warning p-button-sm"
                                            @click="editJournal(slotProps.data)" v-tooltip="editTooltip(slotProps.data)
                                                " :disabled="!canEditJournal(slotProps.data)
                                                    " />
                                        <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-sm"
                                            @click="
                                                deleteJournal(slotProps.data.id)
                                                " v-tooltip="deleteTooltip(slotProps.data)
                                                    " :disabled="!canDeleteJournal(
                                                        slotProps.data,
                                                    )
                                                        " />
                                    </div>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="py-8 text-center text-gray-500">
                                    <i class="pi pi-inbox mb-2 text-4xl"></i>
                                    <p v-if="searchQuery">
                                        No journals found for "{{
                                            searchQuery
                                        }}"
                                    </p>
                                    <p v-else>No journals found</p>
                                    <Button label="Create New Journal" icon="pi pi-plus" class="p-button-outlined mt-4"
                                        @click="openNew" />
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>

        <!-- Journal Dialog -->
        <Dialog v-model:visible="journalDialog" :style="{ width: '90vw', maxWidth: '1400px' }" :header="dialogHeader"
            :modal="true" class="p-fluid">
            <form @submit.prevent="saveJournal">
                <div class="space-y-6">
                    <!-- Header Section -->
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-600 uppercase">
                            <i class="pi pi-file"></i> Journal Header
                        </h3>

                        <!-- Journal Number & Dates -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <label for="journal_number" class="block text-sm font-medium text-gray-700">
                                    Journal Number
                                    <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <InputText id="journal_number" v-model="form.journal_number"
                                        placeholder="Auto-generated" :class="{
                                            'p-invalid':
                                                form.errors.journal_number,
                                        }" class="w-full font-mono" readonly />
                                    <Button v-if="
                                        !form.journal_number &&
                                        isEditable(form.status)
                                    " icon="pi pi-refresh" severity="secondary" @click="generateJournalNumber"
                                        :loading="generatingJournalNumber" />
                                </div>
                                <small class="p-error block text-xs" v-if="form.errors.journal_number">
                                    {{ form.errors.journal_number }}
                                </small>
                            </div>

                            <div class="space-y-2">
                                <label for="journal_date" class="block text-sm font-medium text-gray-700">
                                    Journal Date
                                    <span class="text-red-500">*</span>
                                </label>
                                <Calendar id="journal_date" v-model="form.journal_date" showIcon dateFormat="dd/mm/yy"
                                    :class="{
                                        'p-invalid': form.errors.journal_date,
                                    }" class="w-full" :maxDate="new Date()" :disabled="!isEditable(form.status)" />
                                <small v-if="form.errors.journal_date" class="p-error text-xs">{{
                                    form.errors.journal_date
                                }}</small>
                            </div>
                        </div>

                        <!-- <div class="space-y-2">
                                <label
                                    for="posting_date"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Posting Date
                                    <span class="text-red-500">*</span>
                                </label>
                                <Calendar
                                    id="posting_date"
                                    v-model="form.posting_date"
                                    showIcon
                                    dateFormat="dd/mm/yy"
                                    :class="{
                                        'p-invalid': form.errors.posting_date,
                                    }"
                                    class="w-full"
                                    :minDate="form.journal_date"
                                    :maxDate="new Date()"
                                    :disabled="!isEditable(form.status)"
                                />
                                <small
                                    v-if="form.errors.posting_date"
                                    class="p-error text-xs"
                                    >{{ form.errors.posting_date }}</small
                                >
                            </div> 
                        </div>

                         MDA & Economic Code Selection -->
                        <!-- <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    for="mda_id"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    MDA (Ministry/Department/Agency)
                                    <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    v-model="form.mda_id"
                                    :options="safeMdas"
                                    optionLabel="searchLabel"
                                    optionValue="id"
                                    placeholder="Select MDA"
                                    :filter="true"
                                    :class="{
                                        'p-invalid': form.errors.mda_id,
                                    }"
                                    :key="`mda-dropdown-${form.mda_id}`"
                                    class="w-full"
                                    filterPlaceholder="Search MDA..."
                                    showClear
                                    :disabled="!isEditable(form.status)"
                                    @change="onMdaChange"
                                >
                                    <template #option="slotProps">
                                        <div class="py-2">
                                            <div class="font-medium">
                                                {{ slotProps.option.code }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ slotProps.option.name }}
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                                <small
                                    v-if="form.errors.mda_id"
                                    class="p-error text-xs"
                                    >{{ form.errors.mda_id }}</small
                                >
                            </div>

                            <div class="space-y-2">
                                <label
                                    for="economic_code_id"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Economic Code
                                    <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    v-model="form.economic_code_id"
                                    :options="safeEconomicCodes"
                                    optionLabel="searchLabel"
                                    optionValue="id"
                                    placeholder="Select Economic Code"
                                    :filter="true"
                                    :class="{
                                        'p-invalid':
                                            form.errors.economic_code_id,
                                    }"
                                    :key="`economic-code-dropdown-${form.economic_code_id}`"
                                    class="w-full"
                                    filterPlaceholder="Search economic code..."
                                    showClear
                                    :disabled="!isEditable(form.status)"
                                    @change="onEconomicCodeChange"
                                >
                                    <template #option="slotProps">
                                        <div class="py-2">
                                            <div class="font-medium">
                                                {{ slotProps.option.code }}
                                                <Tag
                                                    :value="
                                                        'Series ' +
                                                        slotProps.option.series
                                                    "
                                                    severity="info"
                                                    size="small"
                                                    class="ml-2"
                                                />
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ slotProps.option.name }}
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                                <small
                                    v-if="form.errors.economic_code_id"
                                    class="p-error text-xs"
                                    >{{ form.errors.economic_code_id }}</small
                                >
                            </div>
                        </div>

                    Administrative Code & Sector 
                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    for="administrative_code_id"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Administrative Code
                                </label>
                                <Dropdown
                                    v-model="form.administrative_code_id"
                                    :options="safeAdministrativeCodes"
                                    optionLabel="searchLabel"
                                    optionValue="id"
                                    placeholder="Select Administrative Code"
                                    :filter="true"
                                    :class="{
                                        'p-invalid':
                                            form.errors.administrative_code_id,
                                    }"
                                    :key="`admin-code-dropdown-${form.administrative_code_id}`"
                                    class="w-full"
                                    filterPlaceholder="Search administrative code..."
                                    showClear
                                    :disabled="!isEditable(form.status)"
                                    @change="onAdministrativeCodeChange"
                                >
                                    <template #option="slotProps">
                                        <div class="py-2">
                                            <div class="font-medium">
                                                {{ slotProps.option.code }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ slotProps.option.name }}
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <div class="space-y-2">
                                <label
                                    for="administrative_sector_code_id"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Administrative Sector
                                </label>
                                <Dropdown
                                    v-model="form.administrative_sector_code_id"
                                    :options="safeAdministrativeSectorCodes"
                                    optionLabel="searchLabel"
                                    optionValue="id"
                                    placeholder="Select Administrative Sector"
                                    :filter="true"
                                    :class="{
                                        'p-invalid':
                                            form.errors
                                                .administrative_sector_code_id,
                                    }"
                                    :key="`admin-sector-dropdown-${form.administrative_sector_code_id}`"
                                    class="w-full"
                                    filterPlaceholder="Search administrative sector..."
                                    showClear
                                    :disabled="
                                        !isEditable(form.status) ||
                                        !form.administrative_code_id
                                    "
                                >
                                    <template #option="slotProps">
                                        <div class="py-2">
                                            <div class="font-medium">
                                                {{ slotProps.option.code }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ slotProps.option.name }}
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                Type:
                                                {{ slotProps.option.type }}
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div> -->






                        <!-- added by Dr. Ben -->
                        <div class="grid mt-4">
                            <div class="col-6">
                                <div class="field">
                                    <label for="sector" class="text-500 mb-1 block text-sm font-semibold">
                                        Sector
                                    </label>
                                    <Dropdown id="sector" v-model="selectedSector"
                                        :options="safeAdministrativeSectorCodes" optionLabel="name"
                                        placeholder="Select Sector" class="w-full" />
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="field">
                                    <label for="mda" class="text-500 mb-1 block text-sm font-semibold">
                                        Ministry / Agency (MDA) *
                                    </label>
                                    <Dropdown id="mda" v-model="form.mda_id" :options="mdaOptions" optionLabel="name"
                                        optionValue="id" placeholder="Select MDA" :filter="true" :class="{
                                            'p-invalid': form.errors.mda_id,
                                        }" :key="`mda-dropdown-${form.mda_id}`" class="w-full"
                                        filterPlaceholder="Search MDA..." showClear @change="onMdaChange"
                                        :disabled="!selectedSector" />
                                    <small class="p-error" v-if="validationErrors.mda_id">{{ validationErrors.mda_id
                                    }}</small>
                                    <small v-else-if="!selectedSector" class="text-500 mt-1 block">Select a Sector
                                        first</small>
                                </div>
                            </div>


                            <div class="col-6">
                                <div class="field">
                                    <label for="form.administrative_code_id"
                                        class="text-500 mb-1 block text-sm font-semibold">
                                        Administrative Code *
                                    </label>
                                    <Dropdown id="administrative_code_id" v-model="form.administrative_code_id"
                                        :options="budgetHeadOptions" optionLabel="label" optionValue="value"
                                        placeholder="Select Code" filter :disabled="!selectedSector" :class="{
                                            'p-invalid':
                                                validationErrors.administrative_code_id,
                                        }" @change="
                                            validationErrors.administrative_code_id = ''
                                            " />
                                    <small class="p-error" v-if="validationErrors.administrative_code_id">{{
                                        validationErrors.administrative_code_id
                                    }}</small>
                                    <small class="text-500 mt-1 block" v-else-if="!form.mda_id">Select an MDA
                                        first</small>
                                    <small class="text-500 mt-1 block" v-else>Source of funds (Top left of
                                        document)</small>
                                </div>
                            </div>


                            <div class="col-4">
                                <div class="field">
                                    <label class="text-500 mb-1 block text-sm font-semibold">
                                        Journal Type
                                    </label>
                                    <!-- <InputText :modelValue="voucherType.toUpperCase()" class="w-full" disabled /> -->

                                    <Dropdown v-model="form.journal_type" :options="JournalTypes" optionLabel="label"
                                        optionValue="value" placeholder="Select Journal Type" class="w-full" :class="{
                                            'p-invalid':
                                                form.errors.journal_type ||
                                                validationErrors.journal_type,
                                        }" @change="validationErrors.journal_type = ''" />
                                    <small class="text-500">Type: {{ journalType }}</small>
                                </div>
                            </div>
                        </div>

                        <!-- End Added by Dr. Ben -->

                        <!-- Description -->
                        <div class="mt-4">
                            <div class="space-y-2">
                                <label for="description" class="block text-sm font-medium text-gray-700">
                                    Description
                                    <span class="text-red-500">*</span>
                                </label>
                                <InputText id="description" v-model="form.description"
                                    placeholder="Enter journal description..." :class="{
                                        'p-invalid': form.errors.description,
                                    }" class="w-full" :maxlength="500" :readonly="!isEditable(form.status)" />
                                <small v-if="form.errors.description" class="p-error text-xs">{{
                                    form.errors.description
                                    }}</small>
                            </div>
                        </div>
                    </div>

                    <!-- Journal Entries Section -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-blue-700 uppercase">
                                <i class="pi pi-table"></i> Journal Entries
                                <Badge :value="form.entries.length" severity="info" size="small" />
                            </h3>
                            <div class="flex gap-2">
                                <Button label="Validate" icon="pi pi-check" severity="secondary"
                                    @click="validateEntries" size="small" :disabled="!isEditable(form.status)" />
                                <Button label="Add Entry" icon="pi pi-plus" severity="primary" @click="addEntry"
                                    size="small" :disabled="!isEditable(form.status)" />
                            </div>
                        </div>

                        <!-- Series Validation Message -->
                        <div v-if="seriesValidationMessage"
                            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <div class="flex items-center gap-2 text-amber-700">
                                <i class="pi pi-info-circle"></i>
                                <span class="font-medium">
                                    {{ seriesValidationMessage }}
                                </span>
                            </div>
                        </div>

                        <!-- Validation Errors -->
                        <div v-if="validationErrors.length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3">
                            <div class="mb-2 flex items-center gap-2 text-red-700">
                                <i class="pi pi-exclamation-triangle"></i>
                                <span class="font-medium">
                                    Validation Errors:
                                </span>
                            </div>
                            <ul class="ml-6 list-disc text-sm text-red-600">
                                <li v-for="(error, index) in validationErrors" :key="index">
                                    {{ error }}
                                </li>
                            </ul>
                        </div>

                        <!-- Journal Entries Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b bg-white">
                                        <th class="px-4 py-2 text-left">#</th>
                                        <th class="px-4 py-2 text-left">
                                            Economic Code
                                        </th>
                                        <th class="px-4 py-2 text-left">
                                            Account (Economic Code Item)
                                        </th>
                                        <th class="px-4 py-2 text-left">
                                            Description
                                        </th>
                                        <th class="px-4 py-2 text-left">
                                            Debit (₦)
                                        </th>
                                        <th class="px-4 py-2 text-left">
                                            Credit (₦)
                                        </th>
                                        <th v-if="isEditable(form.status)" class="px-4 py-2 text-left">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(entry, index) in form.entries"
                                        :key="`entry-row-${index}-${entry.economic_code_id || 'no-ec'}`"
                                        class="border-b hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            {{ index + 1 }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <Dropdown v-model="entry.economic_code_id" :options="safeEconomicCodes"
                                                optionLabel="searchLabel" optionValue="id"
                                                placeholder="Select Economic Code" :filter="true" :class="{
                                                    'p-invalid':
                                                        form.errors[
                                                        `entries.${index}.economic_code_id`
                                                        ],
                                                }"
                                                :key="`entry-economic-code-${index}-${entry.economic_code_id || 'none'}`"
                                                class="w-full" filterPlaceholder="Search economic code..." @change="
                                                    (event) =>
                                                        onEntryEconomicCodeChange(
                                                            index,
                                                            event.value,
                                                        )
                                                " :disabled="!isEditable(form.status) ||
                                                    (index === 0 &&
                                                        !isEditable(
                                                            form.status,
                                                        ))
                                                    ">
                                                <template #option="slotProps">
                                                    <div class="py-2">
                                                        <div class="font-medium">
                                                            {{
                                                                slotProps.option
                                                                    .code
                                                            }}
                                                            <Tag :value="'Series ' +
                                                                slotProps
                                                                    .option
                                                                    .series
                                                                " severity="info" size="small" class="ml-2" />
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{
                                                                slotProps.option
                                                                    .name
                                                            }}
                                                        </div>
                                                    </div>
                                                </template>
                                            </Dropdown>
                                            <small v-if="
                                                form.errors[
                                                `entries.${index}.economic_code_id`
                                                ]
                                            " class="p-error text-xs">
                                                {{
                                                    form.errors[
                                                    `entries.${index}.economic_code_id`
                                                    ]
                                                }}
                                            </small>
                                        </td>
                                        <td class="px-4 py-2">
                                            <Dropdown v-model="entry.account_code" :options="getEconomicCodeItemsForEntry(
                                                index,
                                            )
                                                " optionLabel="searchLabel" optionValue="code"
                                                placeholder="Select Account" :filter="true" :class="{
                                                    'p-invalid':
                                                        form.errors[
                                                        `entries.${index}.account_code`
                                                        ],
                                                }" :key="`entry-account-${index}-${entry.account_code || 'none'}`"
                                                class="w-full" filterPlaceholder="Search account..." :loading="loadingAccountItems[index]
                                                    " :disabled="!isEditable(form.status) ||
                                                        !entry.economic_code_id ||
                                                        loadingAccountItems[index]
                                                        ">
                                                <template #value="slotProps">
                                                    <div v-if="slotProps.value">
                                                        <span>{{
                                                            slotProps.value
                                                            }}</span>
                                                    </div>
                                                    <span v-else>
                                                        Select Account
                                                    </span>
                                                </template>
                                                <template #option="slotProps">
                                                    <div class="py-2">
                                                        <div class="font-medium">
                                                            {{
                                                                slotProps.option
                                                                    .code
                                                            }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{
                                                                slotProps.option
                                                                    .name
                                                            }}
                                                        </div>
                                                    </div>
                                                </template>
                                            </Dropdown>
                                            <small v-if="
                                                form.errors[
                                                `entries.${index}.account_code`
                                                ]
                                            " class="p-error text-xs">
                                                {{
                                                    form.errors[
                                                    `entries.${index}.account_code`
                                                    ]
                                                }}
                                            </small>
                                        </td>
                                        <td class="px-4 py-2">
                                            <InputText v-model="entry.description" placeholder="Entry description..."
                                                :class="{
                                                    'p-invalid':
                                                        form.errors[
                                                        `entries.${index}.description`
                                                        ],
                                                }" :key="`entry-desc-${index}`" class="w-full" :maxlength="500"
                                                :readonly="!isEditable(form.status)
                                                    " />
                                        </td>
                                        <td class="px-4 py-2">
                                            <InputNumber v-model="entry.debit_amount" mode="decimal"
                                                :minFractionDigits="2" :maxFractionDigits="2" :min="0" :class="{
                                                    'p-invalid':
                                                        form.errors[
                                                        `entries.${index}.debit_amount`
                                                        ],
                                                }" :key="`entry-debit-${index}`" class="w-full" @input="
                                                    () => onAmountChange(index)
                                                " :disabled="!isEditable(form.status)
                                                    " />
                                        </td>
                                        <td class="px-4 py-2">
                                            <InputNumber v-model="entry.credit_amount" mode="decimal"
                                                :minFractionDigits="2" :maxFractionDigits="2" :min="0" :class="{
                                                    'p-invalid':
                                                        form.errors[
                                                        `entries.${index}.credit_amount`
                                                        ],
                                                }" :key="`entry-credit-${index}`" class="w-full" @input="
                                                    () => onAmountChange(index)
                                                " :disabled="!isEditable(form.status)
                                                    " />
                                        </td>
                                        <td v-if="isEditable(form.status)" class="px-4 py-2">
                                            <div class="flex gap-1">
                                                <Button icon="pi pi-trash" severity="danger" size="small"
                                                    @click="removeEntry(index)" :disabled="form.entries.length <= 2
                                                        " />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-100 font-semibold">
                                        <td colspan="4" class="px-4 py-2">
                                            Totals
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ formatCurrency(totalDebit) }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ formatCurrency(totalCredit) }}
                                        </td>
                                        <td v-if="isEditable(form.status)"></td>
                                    </tr>
                                    <tr :class="{
                                        'bg-green-50 text-green-700':
                                            isBalanced,
                                        'bg-red-50 text-red-700':
                                            !isBalanced,
                                    }">
                                        <td colspan="4" class="px-4 py-2 font-medium">
                                            Balance Difference
                                        </td>
                                        <td colspan="2" class="px-4 py-2">
                                            {{
                                                formatCurrency(
                                                    balanceDifference,
                                                )
                                            }}
                                            <span v-if="!isBalanced" class="ml-2 text-sm">
                                                (Not Balanced)
                                            </span>
                                            <span v-else class="ml-2 text-sm">
                                                (Balanced)
                                            </span>
                                        </td>
                                        <td v-if="isEditable(form.status)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Additional Information Section -->
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-600 uppercase">
                            <i class="pi pi-info-circle"></i> Additional
                            Information
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- <div class="space-y-2">
                                <label for="reference_number" class="block text-sm font-medium text-gray-700">
                                    Reference Number
                                </label>
                                <InputText id="reference_number" v-model="form.reference_number"
                                    placeholder="e.g., INV-001" :class="{
                                        'p-invalid':
                                            form.errors.reference_number,
                                    }" class="w-full" :readonly="!isEditable(form.status)" />
                                <small v-if="form.errors.reference_number" class="p-error text-xs">{{
                                    form.errors.reference_number }}</small>
                            </div> -->

                            <!-- <div class="space-y-2">
                                <label for="batch_number" class="block text-sm font-medium text-gray-700">
                                    Batch Number
                                </label>
                                <InputText id="batch_number" v-model="form.batch_number"
                                    placeholder="e.g., BATCH-2024-01" :class="{
                                        'p-invalid': form.errors.batch_number,
                                    }" class="w-full" :readonly="!isEditable(form.status)" />
                                <small v-if="form.errors.batch_number" class="p-error text-xs">{{
                                    form.errors.batch_number
                                }}</small>
                            </div> -->

                            <div class="space-y-2" style="display: none;">
                                <label for="financial_year" class="block text-sm font-medium text-gray-700">
                                    Financial Year
                                </label>
                                <InputNumber id="financial_year" v-model="form.financial_year" :min="2000"
                                    :max="new Date().getFullYear() + 1" class="w-full"
                                    :disabled="!isEditable(form.status)" />
                            </div>

                            <div class="space-y-2">
                                <label for="remarks" class="block text-sm font-medium text-gray-700">
                                    Remarks / Notes
                                </label>
                                <Textarea id="remarks" v-model="form.remarks" rows="2" placeholder="Additional notes..."
                                    class="w-full" :readonly="!isEditable(form.status)" />
                            </div>
                        </div>
                    </div>

                    <!-- Status Display -->
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700">Current Status:</span>
                            <Tag :value="form.status" :severity="getStatusSeverity(form.status)" />
                        </div>
                        <small class="text-xs text-gray-500">
                            <span class="text-red-500">*</span> Required fields
                        </small>
                    </div>

                    <!-- Read-only Warning -->
                    <div v-if="!isEditable(form.status)" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-center gap-2 text-amber-700">
                            <i class="pi pi-lock"></i>
                            <span class="text-sm font-medium">
                                This journal is locked for editing because its
                                status is "{{ form.status }}".
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-amber-600">
                            Only journals with editable statuses can be
                            modified. To make changes, please contact an
                            administrator.
                        </p>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex w-full justify-between border-t pt-4">
                    <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="closeDialog"
                        :disabled="form.processing || isSaving" outlined />
                    <div class="flex gap-2">
                        <template v-if="isEditable(form.status)">
                            <Button label="Save as Draft" icon="pi pi-save" severity="secondary"
                                @click="() => saveJournal('draft')" :loading="isSaving" :disabled="isSaving ||
                                    !form.journal_number ||
                                    isLoading
                                    " />
                            <Button label="Submit for Approval" icon="pi pi-send" severity="primary"
                                @click="() => saveJournal('approved')" :loading="isSaving" :disabled="isSaving ||
                                    !form.journal_number ||
                                    isLoading
                                    " />
                        </template>
                        <template v-else>
                            <Button label="Close" icon="pi pi-times" severity="secondary" @click="closeDialog"
                                outlined />
                        </template>
                    </div>
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// PrimeVue Components
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

import axios from 'axios';

const props = defineProps({
    journals: {
        type: Object,
        required: true,
        default: () => ({
            data: [],
            current_page: 1,
            per_page: 10,
            total: 0,
        }),
    },
    filters: Object,
    gl_accounts: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    gl_categories: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
    administrativeCodes: {
        type: Array,
        default: () => [],
    },
    administrativeSectorCodes: {
        type: Array,
        default: () => [],
    },
    mdas: {
        type: Array,
        default: () => [],
    }

});

const toast = useToast();
const journalDialog = ref(false);
const isEdit = ref(false);
const searchQuery = ref(props.filters?.search || '');
const searchTimeout = ref(null);
const validationErrors = ref([]);
const seriesValidationMessage = ref('');
const generatingJournalNumber = ref(false);
const isSaving = ref(false);
const isLoading = ref(false);

const selectedSector = ref(null);
const journalType = ref(null);

const JournalTypes = [
    {
        label: 'Budget',
        value: 'budget',
    },
    {
        label: 'Correction',
        value: 'correction',
    },
    {
        label: 'Salary',
        value: 'salary',
    },
    {
        label: 'Loan',
        value: 'loan',
    },
    {
        label: 'Grant',
        value: 'grant',
    },
    {
        label: 'IGR',
        value: 'igr',
    },
    {
        label: 'FAAC',
        value: 'faac',
    },
];


// Data arrays for dropdowns
// const mdas = ref([]);
// const administrativeCodes = ref([]);
// const administrativeSectorCodes = ref([]);
const economicCodes = ref([]);
const economicCodeItems = ref({}); // Store items by economic code ID
const economicCodeSeriesItems = ref({}); // Store items by series

// Add loading state for account items
const loadingAccountItems = ref({});

// Form for dialog
const form = useForm({
    id: null,
    journal_number: '',
    journal_date: new Date(),
    posting_date: new Date(),
    description: '',
    remarks: '',
    reference_number: '',
    batch_number: '',
    financial_year: new Date().getFullYear(),
    journal_type: '',

    // New fields
    mda_id: null,
    economic_code_id: null,
    administrative_code_id: null,
    administrative_sector_code_id: null,

    status: 'draft',
    is_recurring: false,
    recurring_frequency: null,
    next_recurring_date: null,
    entries: [
        {
            economic_code_id: null,
            account_code: null,
            description: '',
            debit_amount: 0,
            credit_amount: 0,
            cost_center: null,
            project_code: null,
            reference: null,
            tax_code: null,
            tax_amount: 0,
        },
        {
            economic_code_id: null,
            account_code: null,
            description: '',
            debit_amount: 0,
            credit_amount: 0,
            cost_center: null,
            project_code: null,
            reference: null,
            tax_code: null,
            tax_amount: 0,
        },
    ],
});




// const safeAdministrativeCodes = computed(() => {
//     try {
//         return Array.isArray(props.administrativeCodes)
//             ? props.administrativeCodes
//             : [];
//     } catch (error) {
//         console.error('Error accessing administrative codes:', error);
//         return [];
//     }
// });

const safeAdministrativeSectorCodes = computed(() => {


    return props.administrativeCodes.filter(
        (item) =>
            item.name.includes('SECTOR') || item.code.endsWith('0000000000'),
    );

});

const safeEconomicCodes = computed(() => {
    try {
        return Array.isArray(economicCodes.value) ? economicCodes.value : [];
    } catch (error) {
        console.error('Error accessing economic codes:', error);
        return [];
    }
});

// Permission functions for journals
const canEditJournal = (journal) => {
    if (!journal || !journal.status) return false;
    if (usePage().props.auth.userRoles.includes('admin')) return true;
    const status = journal.status.toLowerCase().trim();
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
    ];
    return editableStatuses.includes(status);
};

const canDeleteJournal = (journal) => {
    if (!journal || !journal.status) return false;
    const status = journal.status.toLowerCase().trim();
    const deletableStatuses = ['draft', 'saved'];
    return deletableStatuses.includes(status);
};

// Tooltip functions
const editTooltip = (journal) => {
    if (!canEditJournal(journal)) {
        return `Cannot edit - Status: ${journal.status}`;
    }
    return 'Edit Journal';
};

const deleteTooltip = (journal) => {
    if (!canDeleteJournal(journal)) {
        return `Cannot delete - Status: ${journal.status}`;
    }
    return 'Delete Journal';
};

// Computed properties
const dialogHeader = computed(() => {
    if (!isEditable(form.status)) {
        return `View Journal - ${form.journal_number || 'New'}`;
    }
    return isEdit.value ? 'Edit Journal' : 'New Journal';
});

// Ensure tableData is always an array
const tableData = computed(() => {
    const data = props.journals.data;
    if (Array.isArray(data)) {
        return data;
    }
    if (data && typeof data === 'object' && Array.isArray(data.data)) {
        return data.data;
    }
    return [];
});

// Check if status is editable - for form fields
const isEditable = (status) => {
    if (!status) return true; // New records are editable
    if (usePage().props.auth.userRoles.includes('admin')) return true;
    const normalizedStatus = status.toLowerCase().trim();
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
    ];
    return editableStatuses.includes(normalizedStatus);
};

// Totals computation
const totalDebit = computed(() => {
    try {
        if (!form.entries || !Array.isArray(form.entries)) return 0;
        return form.entries.reduce(
            (total, entry) => total + (Number(entry.debit_amount) || 0),
            0,
        );
    } catch (error) {
        console.error('Error calculating total debit:', error);
        return 0;
    }
});

const totalCredit = computed(() => {
    try {
        if (!form.entries || !Array.isArray(form.entries)) return 0;
        return form.entries.reduce(
            (total, entry) => total + (Number(entry.credit_amount) || 0),
            0,
        );
    } catch (error) {
        console.error('Error calculating total credit:', error);
        return 0;
    }
});

const balanceDifference = computed(() => {
    try {
        return Math.abs(totalDebit.value - totalCredit.value);
    } catch (error) {
        console.error('Error calculating balance difference:', error);
        return 0;
    }
});

const isBalanced = computed(() => {
    try {
        return Math.abs(totalDebit.value - totalCredit.value) < 0.01;
    } catch (error) {
        console.error('Error checking balance:', error);
        return false;
    }
});

// Watch for series validation
watch(
    () => form.entries,
    (newEntries) => {
        validateSeriesRules();
    },
    { deep: true },
);

// Initialize loading states for each entry
const initializeLoadingStates = () => {
    form.entries.forEach((_, index) => {
        loadingAccountItems.value[index] = false;
    });
};

// Functions to load dropdown data
const loadMdas = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/mdas');
        if (response.data.success) {
            mdas.value = response.data.data;
        }
    } catch (error) {
        console.error('Error loading MDAs:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load MDAs',
            life: 3000,
        });
    } finally {
        isLoading.value = false;
    }
};

// const loadAdministrativeCodes = async () => {
//     try {
//         isLoading.value = true;
//         const response = await axios.get('/api/administrative-codes');
//         if (response.data.success) {
//             administrativeCodes.value = response.data.data;
//         }
//     } catch (error) {
//         console.error('Error loading administrative codes:', error);
//     } finally {
//         isLoading.value = false;
//     }
// };

const loadAdministrativeSectorCodes = async (administrativeCodeId) => {
    try {
        if (!administrativeCodeId) {
            administrativeSectorCodes.value = [];
            return;
        }

        isLoading.value = true;
        const response = await axios.get(
            `/api/administrative-sector-codes/${administrativeCodeId}`,
        );
        if (response.data.success) {
            administrativeSectorCodes.value = response.data.data;
        }
    } catch (error) {
        console.error('Error loading administrative sector codes:', error);
        administrativeSectorCodes.value = [];
    } finally {
        isLoading.value = false;
    }
};

const loadEconomicCodes = async () => {
    try {
        isLoading.value = true;
        const response = await axios.get('/api/economic-codes');
        if (response.data.success) {
            economicCodes.value = response.data.data;
        }
    } catch (error) {
        console.error('Error loading economic codes:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load economic codes',
            life: 3000,
        });
    } finally {
        isLoading.value = false;
    }
};

const loadEconomicCodeItems = async (economyCodeId) => {
    try {
        if (!economyCodeId) {
            console.log('No economy code ID provided');
            return [];
        }

        // Check if already loaded
        if (economicCodeItems.value[economyCodeId]) {
            console.log(
                `Items for economy code ${economyCodeId} already loaded`,
            );
            return economicCodeItems.value[economyCodeId];
        }

        console.log(`Loading items for economy code ${economyCodeId}...`);
        const response = await axios.get(
            `/api/economic-code-items/${economyCodeId}`,
        );
        if (response.data.success) {
            economicCodeItems.value[economyCodeId] = response.data.data;
            console.log(
                `Loaded ${response.data.data.length} items for economy code ${economyCodeId}`,
            );
            return response.data.data;
        }
        console.log(`No items found for economy code ${economyCodeId}`);
        return [];
    } catch (error) {
        console.error('Error loading economic code items:', error);
        return [];
    }
};

const loadEconomicCodeItemsBySeries = async (series) => {
    try {
        if (!series) {
            console.log('No series provided');
            return [];
        }

        // Check if already loaded
        if (economicCodeSeriesItems.value[series]) {
            console.log(`Series ${series} items already loaded`);
            return economicCodeSeriesItems.value[series];
        }

        console.log(`Loading items for series ${series}...`);
        const response = await axios.get(
            `/api/economic-code-items-by-series/${series}`,
        );
        if (response.data.success) {
            economicCodeSeriesItems.value[series] = response.data.data;
            console.log(
                `Loaded ${response.data.data.length} items for series ${series}`,
            );
            return response.data.data;
        }
        console.log(`No items found for series ${series}`);
        return [];
    } catch (error) {
        console.error('Error loading economic code items by series:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to load items for series ${series}`,
            life: 3000,
        });
        return [];
    }
};

// Get economic code items for specific entry
const getEconomicCodeItemsForEntry = (index) => {
    try {
        const entry = form.entries[index];
        if (!entry || !entry.economic_code_id) {
            console.log(`Entry ${index}: No economic code ID`);
            return [];
        }

        // Find the economic code to determine series
        const economicCode = safeEconomicCodes.value.find(
            (code) => code.id === entry.economic_code_id,
        );
        if (!economicCode) {
            console.log(`Entry ${index}: Economic code not found`);
            return [];
        }

        console.log(
            `Entry ${index}: Economic code series:`,
            economicCode.series,
        );

        // For Series 1-4, use items by series
        if (economicCode.series >= 1 && economicCode.series <= 4) {
            const seriesItems =
                economicCodeSeriesItems.value[economicCode.series] || [];
            console.log(`Entry ${index}: Series items:`, seriesItems.length);
            return seriesItems;
        }

        // Fallback to items by specific economic code
        const specificItems =
            economicCodeItems.value[entry.economic_code_id] || [];
        console.log(`Entry ${index}: Specific items:`, specificItems.length);
        return specificItems;
    } catch (error) {
        console.error(`Error getting items for entry ${index}:`, error);
        return [];
    }
};

// Generate journal number
const generateJournalNumber = async () => {
    try {
        generatingJournalNumber.value = true;
        const response = await axios.get('/api/generate-journal-number');

        if (response.data.success) {
            form.journal_number = response.data.data.journal_number;
            toast.add({
                severity: 'success',
                summary: 'Journal Number Generated',
                detail: `Journal number: ${response.data.data.journal_number}`,
                life: 3000,
            });
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to generate journal number',
                life: 3000,
            });
        }
    } catch (error) {
        console.error('Error generating journal number:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to generate journal number',
            life: 3000,
        });
    } finally {
        generatingJournalNumber.value = false;
    }
};

// Validate series rules
const validateSeriesRules = () => {
    try {
        seriesValidationMessage.value = '';

        // Rule 1: First entry must be Series 2
        // if (form.entries[0] && form.entries[0].economic_code_id) {
        //     const firstEconomicCode = safeEconomicCodes.value.find(
        //         (code) => code.id === form.entries[0].economic_code_id,
        //     );

        //     if (firstEconomicCode && firstEconomicCode.series !== 2) {
        //         seriesValidationMessage.value =
        //             'First entry must use a Series 2 Economic Code.';
        //         return false;
        //     }
        // }

        return true;
    } catch (error) {
        console.error('Error validating series rules:', error);
        return false;
    }
};

// Event handlers
// const onMdaChange = (event) => {
//     const mdaId = event.value;
//     const selectedMda = safeMdas.value.find((mda) => mda.id === mdaId);

//     if (selectedMda && selectedMda.administrative_code_id) {
//         form.administrative_code_id = selectedMda.administrative_code_id;
//         loadAdministrativeSectorCodes(selectedMda.administrative_code_id);
//     }
// };

// const onAdministrativeCodeChange = (event) => {
//     const codeId = event.value;
//     form.administrative_sector_code_id = null;
//     loadAdministrativeSectorCodes(codeId);
// };

const onEconomicCodeChange = (event) => {
    const codeId = event.value;
    // Load items for this economic code
    loadEconomicCodeItems(codeId);
};

// On entry economic code change - FIXED VERSION
const onEntryEconomicCodeChange = async (index, economicCodeId) => {
    console.log(`Economic code changed for entry ${index}:`, economicCodeId);

    if (!economicCodeId) {
        form.entries[index].account_code = null;
        if (loadingAccountItems.value[index] !== undefined) {
            loadingAccountItems.value[index] = false;
        }
        return;
    }

    // Reset account code when economic code changes
    form.entries[index].account_code = null;

    // Set loading state
    if (loadingAccountItems.value[index] !== undefined) {
        loadingAccountItems.value[index] = true;
    }

    try {
        // Wait for next tick to ensure Vue has completed its current render cycle
        await nextTick();

        // Find the economic code to determine series
        const economicCode = safeEconomicCodes.value.find(
            (code) => code.id === economicCodeId,
        );

        if (economicCode) {
            console.log(`Found economic code:`, economicCode);

            // Load items for this economic code's series
            console.log(`Loading series ${economicCode.series} items...`);
            await loadEconomicCodeItemsBySeries(economicCode.series);

            // Also load items for this specific economic code
            console.log(
                `Loading specific items for economic code ${economicCodeId}...`,
            );
            await loadEconomicCodeItems(economicCodeId);

            // Auto-fill description if empty
            await nextTick();
            if (!form.entries[index].description && economicCode.name) {
                form.entries[index].description = economicCode.name;
            }
        }

        // Validate series rules
        validateSeriesRules();

        // Use a fresh array to ensure reactivity
        await nextTick();
        const updatedEntries = [...form.entries];
        updatedEntries[index] = {
            ...updatedEntries[index],
            economic_code_id: economicCodeId,
        };
        form.entries = updatedEntries;

        // Log state for debugging
        const items = getEconomicCodeItemsForEntry(index);
        console.log(`Available items for entry ${index}:`, items.length);
    } catch (error) {
        console.error('Error handling economic code change:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load account items: ' + error.message,
            life: 3000,
        });
    } finally {
        await nextTick();
        if (loadingAccountItems.value[index] !== undefined) {
            loadingAccountItems.value[index] = false;
        }
    }
};

// Search functions
const performSearch = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    searchTimeout.value = setTimeout(() => {
        router.get(
            '/journals',
            {
                search: searchQuery.value,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 500);
};

// Clear search
const clearSearch = () => {
    searchQuery.value = '';
    performSearch();
};

// Open new journal dialog
const openNew = async () => {
    try {
        // Reset form first
        form.reset();

        // Wait for DOM to be ready
        await nextTick();

        isEdit.value = false;
        form.status = 'draft';
        form.journal_date = new Date();
        form.posting_date = new Date();
        form.financial_year = new Date().getFullYear();
        form.journal_number = '';
        form.journal_type = '';

        // Reset entries
        form.entries = [
            {
                economic_code_id: null,
                account_code: null,
                description: '',
                debit_amount: 0,
                credit_amount: 0,
                cost_center: null,
                project_code: null,
                reference: null,
                tax_code: null,
                tax_amount: 0,
            },
            {
                economic_code_id: null,
                account_code: null,
                description: '',
                debit_amount: 0,
                credit_amount: 0,
                cost_center: null,
                project_code: null,
                reference: null,
                tax_code: null,
                tax_amount: 0,
            },
        ];

        // Initialize loading states
        initializeLoadingStates();

        validationErrors.value = [];
        seriesValidationMessage.value = '';

        // Load data in parallel
        await Promise.all([
            // loadMdas(),
            // loadAdministrativeCodes(),
            loadEconomicCodes(),
        ]);

        // Show dialog only after everything is loaded
        journalDialog.value = true;

        // Auto-set first entry to Series 2 after a short delay
        setTimeout(async () => {
            try {
                if (safeEconomicCodes.value.length > 0) {
                    const series2Code = safeEconomicCodes.value.find(
                        (code) => code.series === 2,
                    );
                    if (series2Code) {
                        await nextTick();
                        form.entries[0].economic_code_id = series2Code.id;

                        // Force reactivity update
                        await nextTick();
                        const updatedEntries = [...form.entries];
                        form.entries = updatedEntries;

                        await onEntryEconomicCodeChange(0, series2Code.id);
                    }
                }

                // Auto-generate journal number
                generateJournalNumber();
            } catch (error) {
                console.error('Error auto-setting series 2:', error);
            }
        }, 100);
    } catch (error) {
        console.error('Error opening new journal dialog:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to initialize new journal',
            life: 3000,
        });
    }
};

// Close dialog
const closeDialog = () => {
    journalDialog.value = false;
    // Wait for dialog to close before resetting
    setTimeout(() => {
        form.reset();
        validationErrors.value = [];
        seriesValidationMessage.value = [];
        loadingAccountItems.value = {};
        isSaving.value = false;
        isLoading.value = false;
    }, 300);
};

// Print journal
const printJournal = (id) => {
    const printUrl = `/journals/${id}/print`;
    window.open(printUrl, '_blank');
};


const editJournal = async (journal) => {
    if (!canEditJournal(journal)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Journal ${journal.journal_number} is "${journal.status}" and cannot be edited.`,
            life: 5000,
        });
        return;
    }

    try {
        // Reset form and states
        form.reset();
        validationErrors.value = [];
        seriesValidationMessage.value = '';
        isEdit.value = true;
        isLoading.value = true;

        // Fetch complete journal data from API
        const response = await axios.get(`/journals/${journal.id}/edit-data`);

        if (!response.data.success) {
            throw new Error(response.data.message || 'Failed to load journal data');
        }

        const journalData = response.data.data;
        console.log('Journal data loaded:', journalData);

        // Wait for DOM to be ready
        await nextTick();

        // Populate form with journal data
        form.id = journalData.id;
        form.journal_number = journalData.journal_number || '';

        // Handle dates - ensure they're Date objects
        form.journal_date = journalData.journal_date
            ? new Date(journalData.journal_date)
            : new Date();

        form.posting_date = journalData.posting_date
            ? new Date(journalData.posting_date)
            : new Date();

        form.description = journalData.description || '';
        form.remarks = journalData.remarks || '';
        form.reference_number = journalData.reference_number || '';
        form.batch_number = journalData.batch_number || '';
        form.financial_year = journalData.financial_year || new Date().getFullYear();

        // Set dropdown values
        form.mda_id = journalData.mda_id || null;
        form.economic_code_id = journalData.economic_code_id || null;
        selectedSector.value = safeAdministrativeSectorCodes.value.find(
            (code) => code.id === journalData.administrative_sector_code_id
        );

        form.administrative_code_id = journalData.administrative_code_id || null;

        console.log('safeAdministrativeSectorCodes:', safeAdministrativeSectorCodes.value);
        form.administrative_sector_code_id = journalData.administrative_sector_code_id || null;
        form.status = journalData.status || 'draft';
        form.journal_type = journalData.journal_type || null;
        console.log('Data:', journalData);
        // selectedSector.value.id = journalData.administrative_sector_code_id;

        // Load all dropdown data first
        await Promise.all([
            // loadMdas(),
            // loadAdministrativeCodes(),
            loadEconomicCodes(),
        ]);

        // Load administrative sector codes if needed
        // if (form.administrative_code_id) {
        //     await loadAdministrativeSectorCodes(form.administrative_code_id);
        // }

        // Initialize entries array
        form.entries = [];

        // Process journal entries
        if (journalData.entries && Array.isArray(journalData.entries)) {
            for (const entry of journalData.entries) {

                console.log('Entryzzz:', entry.economic_code_id);
                form.entries.push({
                    id: entry.id,
                    economic_code_id: entry.economic_code_id,
                    account_code: entry.account_code,
                    description: entry.description || '',
                    debit_amount: parseFloat(entry.debit_amount) || 0,
                    credit_amount: parseFloat(entry.credit_amount) || 0,
                    cost_center: entry.cost_center || null,
                    project_code: entry.project_code || null,
                    reference: entry.reference || null,
                    tax_code: entry.tax_code || null,
                    tax_amount: parseFloat(entry.tax_amount) || 0,
                });
            }
        }

        // Ensure at least 2 entries
        if (form.entries.length < 2) {
            while (form.entries.length < 2) {
                form.entries.push({
                    economic_code_id: null,
                    account_code: null,
                    description: '',
                    debit_amount: 0,
                    credit_amount: 0,
                    cost_center: null,
                    project_code: null,
                    reference: null,
                    tax_code: null,
                    tax_amount: 0,
                });
            }
        }

        // Initialize loading states
        initializeLoadingStates();

        // Load economic code items for each entry
        const loadPromises = form.entries.map(async (entry, index) => {
            if (entry.economic_code_id) {
                loadingAccountItems.value[index] = true;

                try {
                    console.log(`Loading items for entry ${index} with economic_code_id:`, entry.economic_code_id);

                    // Find the economic code to determine series
                    const economicCode = safeEconomicCodes.value.find(
                        (code) => code.id === entry.economic_code_id
                    );

                    if (economicCode) {
                        // Load items by series
                        if (economicCode.series) {
                            await loadEconomicCodeItemsBySeries(economicCode.series);
                        }

                        // Load specific items for this economic code
                        await loadEconomicCodeItems(entry.economic_code_id);

                        // Auto-fill description if empty
                        if (!entry.description) {
                            // Use setTimeout to ensure Vue reactivity
                            setTimeout(() => {
                                form.entries[index].description = economicCode.name || '';
                            }, 100);
                        }
                    }

                    // Force reactivity update
                    await nextTick();
                    const updatedEntries = [...form.entries];
                    updatedEntries[index] = { ...updatedEntries[index] };
                    form.entries = updatedEntries;

                } catch (error) {
                    console.error(`Error loading economic code items for entry ${index}:`, error);
                } finally {
                    loadingAccountItems.value[index] = false;
                }
            }
        });

        await Promise.all(loadPromises);

        // Force final reactivity update
        await nextTick();
        form.entries = [...form.entries];

        // Show the dialog
        journalDialog.value = true;

        // Validate series rules after a short delay
        setTimeout(() => {
            validateSeriesRules();

            // Debug: Log current form state
            console.log('Form state after loading:', {
                mda_id: form.mda_id,
                economic_code_id: form.economic_code_id,
                entries: form.entries.map((e, i) => ({
                    index: i,
                    economic_code_id: e.economic_code_id,
                    account_code: e.account_code,
                    description: e.description,
                })),
            });
        }, 300);

    } catch (error) {
        console.error('Error editing journal:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load journal for editing: ' + error.message,
            life: 3000,
        });
    } finally {
        isLoading.value = false;
    }
};


// Delete journal
const deleteJournal = (id) => {
    const journal = tableData.value.find((item) => item.id === id);

    if (!journal) return;

    if (!canDeleteJournal(journal)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Journal ${journal.journal_number} is "${journal.status}" and cannot be deleted.`,
            life: 5000,
        });
        return;
    }

    if (
        confirm(
            'Are you sure you want to delete this journal? This action cannot be undone.',
        )
    ) {
        router.delete('journals/'+ id, {
            preserveState: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Deleted',
                    detail: 'Journal deleted successfully',
                    life: 3000,
                });
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete journal',
                    life: 3000,
                });
            },
        });
    }
};

// Journal entry management
const addEntry = () => {
    try {
        const newIndex = form.entries.length;
        const newEntry = {
            economic_code_id: null,
            account_code: null,
            description: '',
            debit_amount: 0,
            credit_amount: 0,
            cost_center: null,
            project_code: null,
            reference: null,
            tax_code: null,
            tax_amount: 0,
        };

        form.entries = [...form.entries, newEntry];

        // Initialize loading state for new entry
        loadingAccountItems.value[newIndex] = false;
    } catch (error) {
        console.error('Error adding entry:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to add entry',
            life: 3000,
        });
    }
};

const removeEntry = (index) => {
    try {
        if (form.entries.length > 2) {
            // Create a new array without the entry
            const updatedEntries = form.entries.filter((_, i) => i !== index);
            form.entries = updatedEntries;

            // Remove loading state for deleted entry
            delete loadingAccountItems.value[index];

            // Re-index loading states
            const newLoadingStates = {};
            updatedEntries.forEach((_, i) => {
                newLoadingStates[i] = loadingAccountItems.value[i] || false;
            });
            loadingAccountItems.value = newLoadingStates;

            validateSeriesRules();
        } else {
            toast.add({
                severity: 'warn',
                summary: 'Cannot Remove',
                detail: 'Journal must have at least two entries.',
                life: 3000,
            });
        }
    } catch (error) {
        console.error('Error removing entry:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to remove entry',
            life: 3000,
        });
    }
};

const onAmountChange = (index) => {
    try {
        // Ensure either debit or credit is set, not both
        const entry = form.entries[index];
        if (entry.debit_amount > 0 && entry.credit_amount > 0) {
            toast.add({
                severity: 'warn',
                summary: 'Invalid Entry',
                detail: 'Entry cannot have both debit and credit amounts.',
                life: 3000,
            });
            entry.credit_amount = 0;
        }
    } catch (error) {
        console.error('Error handling amount change:', error);
    }
};

const validateEntries = async () => {
    try {
        // Validate series rules first
        if (!validateSeriesRules()) {
            toast.add({
                severity: 'error',
                summary: 'Series Validation Failed',
                detail: 'Please fix the series validation errors.',
                life: 5000,
            });
            return;
        }

        const response = await axios.post('/api/journals/validate-entries', {
            entries: form.entries,
        });

        if (response.data.success) {
            if (response.data.valid) {
                validationErrors.value = [];
                toast.add({
                    severity: 'success',
                    summary: 'Validation Successful',
                    detail: 'Journal entries are valid and balanced.',
                    life: 3000,
                });
            } else {
                validationErrors.value = response.data.errors;
                toast.add({
                    severity: 'error',
                    summary: 'Validation Failed',
                    detail: 'Please check the validation errors.',
                    life: 5000,
                });
            }
        }
    } catch (error) {
        console.error('Error validating entries:', error);
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Failed to validate journal entries.',
            life: 5000,
        });
    }
};



// Save journal - REVISED VERSION
const saveJournal = async (actionType) => {
    // Check if any dropdown is still loading
    const isAnyLoading = Object.values(loadingAccountItems.value).some(
        (loading) => loading === true,
    );

    if (isAnyLoading || isLoading.value) {
        toast.add({
            severity: 'warn',
            summary: 'Please Wait',
            detail: 'Account items are still loading. Please try again in a moment.',
            life: 3000,
        });
        return;
    }

    // Validate series rules
    if (!validateSeriesRules()) {
        toast.add({
            severity: 'error',
            summary: 'Series Validation Failed',
            detail: 'First entry must use a Series 2 Economic Code.',
            life: 5000,
        });
        return;
    }

    // Validate journal number
    if (!form.journal_number) {
        toast.add({
            severity: 'error',
            summary: 'Journal Number Required',
            detail: 'Please generate a journal number before saving.',
            life: 5000,
        });
        return;
    }

    // Validate that all entries have economic codes selected
    const hasMissingEconomicCodes = form.entries.some(
        (entry) => !entry.economic_code_id,
    );

    if (hasMissingEconomicCodes) {
        toast.add({
            severity: 'error',
            summary: 'Missing Economic Codes',
            detail: 'Please select an economic code for all entries.',
            life: 5000,
        });
        return;
    }

    // Validate that all entries have account codes selected
    const hasMissingAccountCodes = form.entries.some(
        (entry) => !entry.account_code,
    );

    if (hasMissingAccountCodes) {
        toast.add({
            severity: 'error',
            summary: 'Missing Account Codes',
            detail: 'Please select an account for all entries.',
            life: 5000,
        });
        return;
    }

    // Validate that debit or credit amounts are set
    const hasMissingAmounts = form.entries.some(
        (entry) =>
            (!entry.debit_amount || entry.debit_amount <= 0) &&
            (!entry.credit_amount || entry.credit_amount <= 0),
    );

    if (hasMissingAmounts) {
        toast.add({
            severity: 'error',
            summary: 'Missing Amounts',
            detail: 'Please enter either debit or credit amount for all entries.',
            life: 5000,
        });
        return;
    }

    // Validate journal is balanced
    if (!isBalanced.value) {
        toast.add({
            severity: 'error',
            summary: 'Journal Not Balanced',
            detail: 'Total debit must equal total credit.',
            life: 5000,
        });
        return;
    }

    // Set the status based on the action
    let newStatus = 'draft';
    if (actionType === 'pending') {
        newStatus = 'approved';
    }
    if (actionType === 'approved') {
        newStatus = 'approved';
    }

    form.status = newStatus;
    // form.administrative_code_id = .value.id;
    form.administrative_sector_code_id = selectedSector.value.id;

    // Create a properly formatted data object
    const submitData = {
        ...form.data(),
        journal_date: formatDateForBackend(form.journal_date),
        posting_date: formatDateForBackend(form.posting_date),
        entries: form.entries.map((entry) => ({
            ...entry,
            debit_amount: parseFloat(entry.debit_amount) || 0,
            credit_amount: parseFloat(entry.credit_amount) || 0,
            tax_amount: parseFloat(entry.tax_amount) || 0,
        })),
    };

    // Remove empty values that might cause issues
    Object.keys(submitData).forEach((key) => {
        if (
            submitData[key] === null ||
            submitData[key] === undefined ||
            submitData[key] === ''
        ) {
            delete submitData[key];
        }
    });

    try {
        isSaving.value = true;

        // Wait for any pending DOM updates
        await nextTick();

        console.log('Submitting data:', submitData); // Debug log

        if (isEdit.value) {
            // For update, use PUT method
            const response = await axios.put(
                `/journals/${form.id}`,
                submitData,
                {
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                },
            )

                .then(response => {
                    if (response.data.success) {
                        closeDialog();
                        toast.add({
                            severity: 'success',
                            summary: 'Updated',
                            detail: `Journal ${form.status === 'pending' ? 'submitted for approval' : 'saved as draft'} successfully`,
                            life: 3000,
                        });
                        // Refresh the page data
                        router.reload({ only: ['journals'] });
                    } else {
                        throw new Error(response.data.message || 'Update failed');
                    }
                })
                .catch(error => {
                    // This block is executed if there's an error (e.g., network error, status 4xx, 5xx)
                    console.error('There was an error updating the Journal:', error.message);
                })


        } else {
            // For create, use POST method
            const response = await axios.post('/journals', submitData, {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
            }).then(response => {

                if (response.data.success) {
                    closeDialog();
                    toast.add({
                        severity: 'success',
                        summary: 'Created',
                        detail: `Journal ${form.status === 'pending' ? 'submitted for approval' : 'saved as draft'} successfully`,
                        life: 3000,
                    });
                    // Refresh the page data
                    router.reload({ only: ['journals'] });
                } else {
                    throw new Error(response.data.message || 'Creation failed');
                }

            })
                .catch(error => {
                    // This block is executed if there's an error (e.g., network error, status 4xx, 5xx)
                    console.error('There was an error creating this journal:', error.message);
                });


        }
    } catch (error) {
        console.error('Error saving journal:', error);

        let errorMessage = 'Failed to save journal. Please try again.';
        let errorDetail = error.message;

        if (error.response) {
            // The request was made and the server responded with a status code
            console.error('Response error:', error.response);
            errorMessage =
                error.response.data.message || 'Server error occurred';

            // Handle validation errors
            if (error.response.data.errors) {
                const validationErrors = error.response.data.errors;
                errorDetail = Object.values(validationErrors).flat().join(', ');
            }
        } else if (error.request) {
            // The request was made but no response was received
            console.error('Request error:', error.request);
            errorMessage =
                'No response from server. Please check your connection.';
        }

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: errorDetail,
            life: 5000,
        });
    } finally {
        isSaving.value = false;
    }
};

// Helper function to format dates for backend
const formatDateForBackend = (dateValue) => {
    if (!dateValue) return null;

    try {
        const date = new Date(dateValue);
        if (isNaN(date.getTime())) return null;

        // Format as YYYY-MM-DD (ISO format without time)
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    } catch (error) {
        console.error('Error formatting date:', error);
        return null;
    }
};

// Format currency
const formatCurrency = (value) => {
    try {
        if (!value && value !== 0) return '₦0.00';
        const numValue = Number(value);
        if (isNaN(numValue)) return '₦0.00';
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(numValue || 0);
    } catch (error) {
        console.error('Error formatting currency:', error);
        return '₦0.00';
    }
};

// Format date
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch (e) {
        return 'N/A';
    }
};

// Get status severity
const getStatusSeverity = (status) => {
    if (!status) return 'secondary';
    const normalizedStatus = status.toLowerCase().trim();

    switch (normalizedStatus) {
        case 'draft':
        case 'saved':
            return 'warning';
        case 'pending':
        case 'submitted':
            return 'info';
        case 'approved':
        case 'completed':
        case 'posted':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'failed':
        case 'returned':
        case 'sent back':
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
};

// Pagination handlers
const onPageChange = (event) => {
    const page = Math.floor(event.first / event.rows) + 1;
    const queryParams = {
        page: page,
        per_page: event.rows,
    };

    if (searchQuery.value) {
        queryParams.search = searchQuery.value;
    }

    router.get(route('journals.index'), queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onRowsPerPageChange = (event) => {
    const queryParams = {
        page: 1,
        per_page: event.value,
    };

    if (searchQuery.value) {
        queryParams.search = searchQuery.value;
    }

    router.get(route('journals.index'), queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Lifecycle
onMounted(() => {
    if (props.flash?.message) {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: props.flash.message,
            life: 3000,
        });
    }

    // console.log(props.administrativeSectorCodes);
});


const mdaOptions = computed(() => {
    // console.log(props.mdas, selectedSector.value);
    if (!selectedSector.value) return [];


    const mdaz = props.mdas
        .filter((item) => item.administrative_code_id === selectedSector.value.id);

    console.log(mdaz, "ben");

    return mdaz;
});

// const sectorOptions = computed(() => {
//     return props.administrativeCodes.filter(
//         (item: any) =>
//             item.name.includes('SECTOR') || item.code.endsWith('0000000000'),
//     );
// });


const budgetHeadOptions = computed(() => {
    // if (!form.mda_id) return [];
    if (selectedSector.value === null) return [];

    return props.administrativeSectorCodes
        .filter((head) => head.administrative_code_id === selectedSector.value?.id)
        .map((head) => ({
            value: head.id,
            label: `${head.code} - ${head.name}`,
        }));
});


</script>

<style scoped>
:deep(.p-datatable .p-column-header-content) {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
}

:deep(.p-datatable-tbody tr:hover) {
    background-color: #f8fafc !important;
}

:deep(.p-dialog .p-dialog-header) {
    border-bottom: 1px solid #e5e7eb;
    padding: 1.25rem 1.5rem;
}

:deep(.p-dialog .p-dialog-content) {
    padding: 1.5rem;
}

:deep(.p-dialog .p-dialog-footer) {
    border-top: 1px solid #e5e7eb;
    padding: 1rem 1.5rem;
}

:deep(.p-dropdown) {
    width: 100%;
}

:deep(.p-button:disabled) {
    opacity: 0.6;
    cursor: not-allowed;
}

:deep(.p-inputtext:disabled),
:deep(.p-calendar:disabled),
:deep(.p-dropdown:disabled),
:deep(.p-inputnumber:disabled),
:deep(.p-textarea:disabled) {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
