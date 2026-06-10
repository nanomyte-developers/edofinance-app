<template>
    <AppLayout>
        <Head title="Remittances Management" />
        <Toast />

        <div class="grid">
            <div class="col-12">
                <div class="card">
                    <!-- Toolbar -->
                    <div
                        class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h5 class="m-0 text-xl font-bold text-gray-800">
                                Remittances
                            </h5>
                            <p class="mt-1 text-gray-600">
                                Total: {{ remittances.total || 0 }} remittances
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                label="New Remittance"
                                icon="pi pi-plus"
                                class="p-button-success"
                                @click="openNew"
                            />
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="mb-6 px-4">
                        <div
                            class="flex flex-col gap-4 md:flex-row md:items-center"
                        >
                            <div class="flex-1">
                                <div class="relative w-full">
                                    <i
                                        class="pi pi-search absolute top-1/2 left-3.5 -translate-y-1/2 text-gray-400"
                                    />
                                    <InputText
                                        v-model="searchQuery"
                                        @input="performSearch"
                                        placeholder="Search remittances..."
                                        class="w-full pl-11"
                                    />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    label="Clear"
                                    icon="pi pi-times"
                                    severity="secondary"
                                    @click="clearSearch"
                                    :disabled="!searchQuery"
                                    outlined
                                />
                                <Button
                                    label="Search"
                                    icon="pi pi-search"
                                    @click="performSearch"
                                    severity="info"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="px-4">
                        <DataTable
                            :value="tableData"
                            paginator
                            :rows="remittances.per_page || 10"
                            :totalRecords="remittances.total"
                            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} remittances"
                            :rowsPerPageOptions="[10, 20, 50]"
                            :first="
                                (remittances.current_page - 1) *
                                remittances.per_page
                            "
                            @page="onPageChange"
                            @rows-change="onRowsPerPageChange"
                            responsiveLayout="scroll"
                            dataKey="id"
                            class="p-datatable-sm"
                            stripedRows
                            scrollable
                            scrollHeight="flex"
                        >
                            <Column
                                field="receipt_number"
                                header="Receipt #"
                                sortable
                                :style="{ minWidth: '120px' }"
                            >
                                <template #body="slotProps">
                                    <Link
                                        :href="
                                            '/remittances/' + slotProps.data.id
                                        "
                                        class="rounded px-1 py-0.5 font-semibold text-blue-600 transition-all hover:text-blue-800 hover:underline focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                                    >
                                        {{
                                            slotProps.data.receipt_number ||
                                            'N/A'
                                        }}
                                    </Link>
                                </template>
                            </Column>

                            <Column
                                field="transfer_date"
                                header="Transfer Date"
                                sortable
                                :style="{ minWidth: '130px' }"
                            >
                                <template #body="slotProps">
                                    {{
                                        formatDate(slotProps.data.transfer_date)
                                    }}
                                </template>
                            </Column>

                            <Column
                                field="source_bank"
                                header="Source Account"
                                sortable
                                :style="{ minWidth: '200px' }"
                            >
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Tag
                                                v-if="
                                                    slotProps.data
                                                        .source_bank_details
                                                        ?.tag
                                                "
                                                :value="
                                                    slotProps.data
                                                        .source_bank_details.tag
                                                "
                                                severity="info"
                                                size="small"
                                            />
                                            <span class="font-medium">
                                                {{
                                                    slotProps.data
                                                        .source_bank_details
                                                        .bank_name || 'N/A'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="ml-7 text-xs text-gray-600"
                                            v-if="
                                                slotProps.data
                                                    .source_bank_details
                                                    ?.account_number
                                            "
                                        >
                                            {{
                                                slotProps.data
                                                    .source_bank_details.title
                                            }}
                                            â€¢
                                            {{
                                                slotProps.data
                                                    .source_bank_details
                                                    .account_number
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column
                                field="destination_bank"
                                header="Destination Account"
                                sortable
                                :style="{ minWidth: '200px' }"
                            >
                                <template #body="slotProps">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <Tag
                                                v-if="
                                                    slotProps.data
                                                        .destination_bank_details
                                                        ?.tag
                                                "
                                                :value="
                                                    slotProps.data
                                                        .destination_bank_details
                                                        .tag
                                                "
                                                severity="success"
                                                size="small"
                                            />
                                            <span class="font-medium">
                                                {{
                                                    slotProps.data
                                                        .destination_bank_details
                                                        .bank_name || 'N/A'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="ml-7 text-xs text-gray-600"
                                            v-if="
                                                slotProps.data
                                                    .destination_bank_details
                                                    ?.account_number
                                            "
                                        >
                                            {{
                                                slotProps.data
                                                    .destination_bank_details
                                                    .title
                                            }}
                                            â€¢
                                            {{
                                                slotProps.data
                                                    .destination_bank_details
                                                    .account_number
                                            }}
                                        </div>
                                    </div>
                                </template>
                            </Column>

                            <Column
                                field="amount"
                                header="Amount"
                                sortable
                                :style="{ minWidth: '150px' }"
                            >
                                <template #body="slotProps">
                                    <div class="flex flex-col">
                                        <span class="font-bold">
                                            {{
                                                formatCurrency(
                                                    slotProps.data.amount,
                                                )
                                            }}
                                        </span>
                                        <small
                                            v-if="slotProps.data.amount > 0"
                                            class="text-xs text-gray-500"
                                        >
                                            {{
                                                convertNumberToWords(
                                                    slotProps.data.amount,
                                                )
                                            }}
                                        </small>
                                    </div>
                                </template>
                            </Column>

                            <Column
                                field="status"
                                header="Status"
                                sortable
                                :style="{ minWidth: '140px' }"
                            >
                                <template #body="slotProps">
                                    <Tag
                                        :value="
                                            slotProps.data.status || 'Unknown'
                                        "
                                        :severity="
                                            getStatusSeverity(
                                                slotProps.data.status,
                                            )
                                        "
                                    />
                                </template>
                            </Column>

                            <Column
                                header="Actions"
                                :exportable="false"
                                :style="{ minWidth: '100px' }"
                            >
                                <template #body="slotProps">
                                    <div class="flex gap-1">
                                        <!-- <Button
                                            icon="pi pi-eye"
                                            class="p-button-rounded p-button-secondary p-button-sm"
                                            v-tooltip="'View Details'"
                                            @click="
                                                viewRemittance(
                                                    slotProps.data.id,
                                                )
                                            "
                                        /> -->
                                        <Button
                                            icon="pi pi-print"
                                            class="p-button-rounded p-button-secondary p-button-sm"
                                            v-tooltip="'Print Remittance'"
                                            @click="
                                                printRemittance(
                                                    slotProps.data.id,
                                                )
                                            "
                                        />
                                        <Button
                                        
                                            icon="pi pi-pencil"
                                            class="p-button-rounded p-button-warning p-button-sm"
                                            @click="
                                                editRemittance(slotProps.data)
                                            "
                                            v-tooltip="
                                                editTooltip(slotProps.data)
                                            "
                                            :disabled="
                                                !canEditRemittance(
                                                    slotProps.data,
                                                )
                                            "
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            class="p-button-rounded p-button-danger p-button-sm"
                                            @click="
                                                deleteRemittance(
                                                    slotProps.data.id,
                                                )
                                            "
                                            v-tooltip="
                                                deleteTooltip(slotProps.data)
                                            "
                                            :disabled="
                                                !canDeleteRemittance(
                                                    slotProps.data,
                                                )
                                            "
                                        />
                                    </div>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="py-8 text-center text-gray-500">
                                    <i class="pi pi-inbox mb-2 text-4xl"></i>
                                    <p v-if="searchQuery">
                                        No remittances found for "{{
                                            searchQuery
                                        }}"
                                    </p>
                                    <p v-else>No remittances found</p>
                                    <Button
                                        label="Create New Remittance"
                                        icon="pi pi-plus"
                                        class="p-button-outlined mt-4"
                                        @click="openNew"
                                    />
                                </div>
                            </template>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remittance Dialog -->
        <Dialog
            v-model:visible="remittanceDialog"
            :style="{ width: '50vw', maxWidth: '800px' }"
            :header="dialogHeader"
            :modal="true"
            class="p-fluid"
        >
            <form @submit.prevent="saveRemittance">
                <div class="space-y-6">
                    <!-- Treasury Details Section -->
                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <h3
                            class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-600 uppercase"
                        >
                            <i class="pi pi-briefcase"></i> Treasury Details
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    for="receipt_number"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Receipt Number
                                    <span class="text-red-500">*</span>
                                </label>
                                <InputText
                                    id="receipt_number"
                                    v-model="form.receipt_number"
                                    @input="
                                        form.receipt_number =
                                            $event.target.value.toUpperCase()
                                    "
                                    placeholder="e.g., B638799"
                                    :class="{
                                        'p-invalid': form.errors.receipt_number,
                                    }"
                                    class="w-full font-mono tracking-wider uppercase"
                                    style="text-transform: uppercase"
                                    :maxlength="20"
                                    :readonly="!isEditable(form.status)"
                                />
                                <small
                                    class="p-error block text-xs"
                                    v-if="form.errors.receipt_number"
                                >
                                    {{ form.errors.receipt_number }}
                                </small>
                                <small class="text-xs text-gray-500">
                                    Enter in uppercase (e.g., B638799)
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Info Section -->
                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <h3
                            class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-600 uppercase"
                        >
                            <i class="pi pi-calendar"></i> Transaction Info
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    for="transfer_date"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Transfer Date
                                    <span class="text-red-500">*</span>
                                </label>
                                <Calendar
                                    id="transfer_date"
                                    v-model="form.transfer_date"
                                    showIcon
                                    dateFormat="dd/mm/yy"
                                    :class="{
                                        'p-invalid': form.errors.transfer_date,
                                    }"
                                    class="w-full"
                                    :maxDate="new Date()"
                                    :disabled="!isEditable(form.status)"
                                />
                                <small
                                    v-if="form.errors.transfer_date"
                                    class="p-error text-xs"
                                    >{{ form.errors.transfer_date }}</small
                                >
                            </div>
                            <div class="space-y-2">
                                <label
                                    for="amount"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Amount (â‚¦)
                                    <span class="text-red-500">*</span>
                                </label>
                                <InputNumber
                                    id="amount"
                                    v-model="form.amount"
                                    mode="currency"
                                    currency="NGN"
                                    locale="en-NG"
                                    :min="0"
                                    :class="{ 'p-invalid': form.errors.amount }"
                                    class="w-full"
                                    :disabled="!isEditable(form.status)"
                                />
                                <small
                                    v-if="form.errors.amount"
                                    class="p-error text-xs"
                                    >{{ form.errors.amount }}</small
                                >
                            </div>
                        </div>

                        <!-- Amount in Words -->
                        <div class="mt-4 space-y-2">
                            <label
                                class="block text-sm font-medium text-gray-700"
                            >
                                Amount in Words
                            </label>
                            <div class="rounded-md bg-gray-100 p-3">
                                <p class="text-sm font-medium text-gray-800">
                                    {{ amountInWords }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Section -->
                    <div
                        class="rounded-lg border border-blue-200 bg-blue-50 p-4"
                    >
                        <h3
                            class="mb-4 flex items-center gap-2 text-sm font-semibold text-blue-700 uppercase"
                        >
                            <i class="pi pi-building"></i> Banking Information
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Source Bank Dropdown -->
                            <div class="space-y-2">
                                <label
                                    for="source_bank"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Source Bank
                                    <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    v-model="form.source_bank_id"
                                    :options="bankActivities"
                                    optionLabel="searchLabel"
                                    optionValue="id"
                                    placeholder="Select Source Bank"
                                    :filter="true"
                                    :class="{
                                        'p-invalid': form.errors.source_bank,
                                    }"
                                    class="w-full"
                                    filterPlaceholder="Search bank..."
                                    showClear
                                    @change="onSourceBankChange"
                                    :disabled="!isEditable(form.status)"
                                >
                                    <template #option="slotProps">
                                        <div
                                            class="flex items-center gap-3 py-2"
                                        >
                                            <Tag
                                                :value="
                                                    slotProps.option.tag
                                                        ?.substring(0, 5)
                                                        ?.toUpperCase() ||
                                                    'BANK'
                                                "
                                                severity="info"
                                                size="small"
                                            />
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{
                                                    slotProps.option.bank_name
                                                }}</span>
                                                <span
                                                    class="text-xs text-gray-500"
                                                >
                                                    {{ slotProps.option.title }}
                                                    â€¢
                                                    {{
                                                        slotProps.option
                                                            .account_number
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                    <template #value="slotProps">
                                        <div
                                            v-if="slotProps.value"
                                            class="flex items-center gap-2"
                                        >
                                            <Tag
                                                :value="
                                                    selectedSourceBank?.tag
                                                        ?.substring(0, 5)
                                                        ?.toUpperCase() ||
                                                    'BANK'
                                                "
                                                severity="info"
                                                size="small"
                                            />
                                            <span>{{
                                                selectedSourceBank?.bank_name ||
                                                'Select bank...'
                                            }}</span>
                                        </div>
                                        <span v-else class="text-gray-400">{{
                                            slotProps.placeholder
                                        }}</span>
                                    </template>
                                </Dropdown>
                                <small
                                    v-if="form.errors.source_bank"
                                    class="p-error text-xs"
                                    >{{ form.errors.source_bank }}</small
                                >
                            </div>

                            <!-- Destination Bank Dropdown -->
                            <div class="space-y-2">
                                <label
                                    for="destination_bank"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Destination Bank
                                    <span class="text-red-500">*</span>
                                </label>
                                <Dropdown
                                    v-model="form.destination_bank_id"
                                    :options="bankActivities"
                                    optionLabel="searchLabel"
                                    optionValue="id"
                                    placeholder="Select Destination Bank"
                                    :filter="true"
                                    :class="{
                                        'p-invalid':
                                            form.errors.destination_bank,
                                    }"
                                    class="w-full"
                                    filterPlaceholder="Search bank..."
                                    showClear
                                    @change="onDestinationBankChange"
                                    :disabled="!isEditable(form.status)"
                                >
                                    <template #option="slotProps">
                                        <div
                                            class="flex items-center gap-3 py-2"
                                        >
                                            <Tag
                                                :value="
                                                    slotProps.option.tag
                                                        ?.substring(0, 4)
                                                        ?.toUpperCase() ||
                                                    'BANK'
                                                "
                                                severity="success"
                                                size="small"
                                            />
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{
                                                    slotProps.option.bank_name
                                                }}</span>
                                                <span
                                                    class="text-xs text-gray-500"
                                                >
                                                    {{ slotProps.option.title }}
                                                    â€¢
                                                    {{
                                                        slotProps.option
                                                            .account_number
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </template>
                                    <template #value="slotProps">
                                        <div
                                            v-if="slotProps.value"
                                            class="flex items-center gap-2"
                                        >
                                            <Tag
                                                :value="
                                                    selectedDestinationBank?.tag
                                                        ?.substring(0, 4)
                                                        ?.toUpperCase() ||
                                                    'BANK'
                                                "
                                                severity="success"
                                                size="small"
                                            />
                                            <span>{{
                                                selectedDestinationBank?.bank_name ||
                                                'Select bank...'
                                            }}</span>
                                        </div>
                                        <span v-else class="text-gray-400">{{
                                            slotProps.placeholder
                                        }}</span>
                                    </template>
                                </Dropdown>
                                <small
                                    v-if="form.errors.destination_bank"
                                    class="p-error text-xs"
                                    >{{ form.errors.destination_bank }}</small
                                >
                            </div>

                            <!-- Warning for same accounts -->
                            <div
                                v-if="accountsAreSame && !isEdit"
                                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3"
                            >
                                <div
                                    class="flex items-center gap-2 text-amber-700"
                                >
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <span class="text-sm font-medium">
                                        âš ï¸ Warning: Source account and
                                        destination account cannot be the same.
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-amber-600">
                                    You have selected the same bank account for
                                    both source and destination.
                                    <template v-if="selectedSourceBank">
                                        Account:
                                        {{ selectedSourceBank.account_number }}
                                        - {{ selectedSourceBank.title }}
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Narration Section -->
                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <h3
                            class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-600 uppercase"
                        >
                            <i class="pi pi-file-edit"></i> Narration
                        </h3>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label
                                    for="narration"
                                    class="block text-sm font-medium text-gray-700"
                                >
                                    Narration / Description
                                </label>
                                <Button
                                    v-if="
                                        form.source_bank_id &&
                                        form.destination_bank_id &&
                                        isEditable(form.status)
                                    "
                                    label="Auto-fill"
                                    icon="pi pi-magic"
                                    size="small"
                                    severity="secondary"
                                    @click="handleAutoFillNarration"
                                    outlined
                                    class="text-xs"
                                />
                            </div>
                            <Textarea
                                id="narration"
                                v-model="form.narration"
                                rows="3"
                                :placeholder="
                                    form.source_bank_id &&
                                    form.destination_bank_id
                                        ? 'Auto-generated narration will appear here...'
                                        : 'Select source and destination banks to auto-generate narration...'
                                "
                                :class="{ 'p-invalid': form.errors.narration }"
                                class="w-full"
                                :maxlength="500"
                                :readonly="
                                    !isEditable(form.status) ||
                                    (!isEdit &&
                                        form.source_bank_id &&
                                        form.destination_bank_id)
                                "
                            />
                            <div class="flex justify-between">
                                <small
                                    v-if="form.errors.narration"
                                    class="p-error text-xs"
                                >
                                    {{ form.errors.narration }}
                                </small>
                                <small class="text-xs text-gray-500">
                                    {{ form.narration?.length || 0 }}/500
                                    characters
                                </small>
                            </div>

                            <!-- Show auto-narration preview -->
                            <div
                                v-if="
                                    autoNarration &&
                                    (!form.narration ||
                                        form.narration !== autoNarration) &&
                                    isEditable(form.status)
                                "
                                class="mt-2 rounded border border-blue-100 bg-blue-50 p-2"
                            >
                                <p class="text-xs text-blue-700">
                                    <i class="pi pi-info-circle mr-1"></i>
                                    Auto-narration available. Click "Auto-fill"
                                    to use.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Status Display -->
                    <div
                        class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700"
                                >Current Status:</span
                            >
                            <Tag
                                :value="form.status"
                                :severity="getStatusSeverity(form.status)"
                            />
                        </div>
                        <small class="text-xs text-gray-500">
                            <span class="text-red-500">*</span> Required fields
                        </small>
                    </div>

                    <!-- Read-only Warning -->
                    <div
                        v-if="!isEditable(form.status)"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-4"
                    >
                        <div class="flex items-center gap-2 text-amber-700">
                            <i class="pi pi-lock"></i>
                            <span class="text-sm font-medium">
                                This remittance is locked for editing because
                                its status is "{{ form.status }}".
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-amber-600">
                            Only remittances with "Draft" status can be edited.
                            To make changes, please contact an administrator to
                            revert the status to Draft.
                        </p>
                    </div>
                </div>
            </form>

            <!-- <template #footer>
                <div class="flex w-full justify-between border-t pt-4">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        severity="secondary"
                        @click="remittanceDialog = false"
                        :disabled="form.processing"
                        outlined
                    />
                    <div class="flex gap-2">
                        <template v-if="isEditable(form.status)">
                            <Button
                                label="Save as Draft"
                                icon="pi pi-save"
                                severity="secondary"
                                @click="() => saveRemittance('Draft')"
                                :loading="form.processing"
                                :disabled="form.processing"
                            />
                            <Button
                                label="Submit for Approval"
                                icon="pi pi-send"
                                severity="primary"
                                @click="
                                    () => saveRemittance('Pending Approval')
                                "
                                :loading="form.processing"
                                :disabled="form.processing"
                            />
                        </template>
                        <template v-else>
                            <Button
                                label="Close"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="remittanceDialog = false"
                                outlined
                            />
                        </template>
                    </div>
                </div>
            </template> -->
            <template #footer>
                <div class="flex w-full justify-between border-t pt-4">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        severity="secondary"
                        @click="remittanceDialog = false"
                        :disabled="form.processing"
                        outlined
                    />
                    <div class="flex gap-2">
                        <template v-if="isEditable(form.status)">
                            <Button
                                label="Save as Draft"
                                icon="pi pi-save"
                                severity="secondary"
                                @click="() => saveRemittance('draft')"
                                :loading="form.processing"
                                :disabled="form.processing"
                            />
                            <Button
                                label="Submit for Approval"
                                icon="pi pi-send"
                                severity="primary"
                                @click="() => saveRemittance('submit')"
                                :loading="form.processing"
                                :disabled="form.processing"
                            />
                        </template>
                        <template v-else>
                            <Button
                                label="Close"
                                icon="pi pi-times"
                                severity="secondary"
                                @click="remittanceDialog = false"
                                outlined
                            />
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
import { computed, onMounted, ref } from 'vue';

// PrimeVue Components
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

const props = defineProps({
    remittances: {
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
    bank_activities: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

const toast = useToast();
const remittanceDialog = ref(false);
const isEdit = ref(false);
const searchQuery = ref(props.filters?.search || '');
const searchTimeout = ref(null);

// Form for dialog
const form = useForm({
    id: null,
    treasury: '',
    receipt_number: '',
    transfer_date: new Date(),
    source_bank_id: null,
    destination_bank_id: null,
    source_bank: '',
    destination_bank: '',
    amount: 0,
    narration: '',
    status: 'Draft',
});

// Fixed voucher permission functions for remittances
const canEditRemittance = (remittance) => {
    if (usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')) {
        return true;
    }
    if (!remittance || !remittance.status) return false;

    const status = remittance.status.toLowerCase().trim();

    // Allow editing for these statuses - matches voucher logic
    const editableStatuses = [
        'draft',
        'saved',
        'sent back',
        'returned',
        'declined',
        'rejected',
    ];

    const canEdit = editableStatuses.includes(status);

    return canEdit;
};

const canDeleteRemittance = (remittance) => {
    if (!remittance || !remittance.status) return false;

    const status = remittance.status.toLowerCase().trim();

    // Allow deletion only for drafts and saved remittances
    const deletableStatuses = ['draft', 'saved'];

    const canDelete = deletableStatuses.includes(status);

    return canDelete;
};

// Tooltip functions
const editTooltip = (remittance) => {
    if (!canEditRemittance(remittance)) {
        return `Cannot edit - Status: ${remittance.status}`;
    }
    return 'Edit Remittance';
};

const deleteTooltip = (remittance) => {
    if (!canDeleteRemittance(remittance)) {
        return `Cannot delete - Status: ${remittance.status}`;
    }
    return 'Delete Remittance';
};

// Computed properties
const dialogHeader = computed(() => {
    if (!isEditable(form.status)) {
        return `View Remittance - ${form.receipt_number || 'New'}`;
    }
    return isEdit.value ? 'Edit Remittance' : 'New Remittance';
});

// Bank activities with search label
const bankActivities = computed(() => {
    if (!props.bank_activities || !Array.isArray(props.bank_activities)) {
        console.warn('bank_activities is not an array or is undefined');
        return [];
    }

    return props.bank_activities.map((item) => {
        if (item.searchLabel) {
            return item;
        }

        return {
            ...item,
            searchLabel: `${item.bank_name || ''} - ${item.title || ''} (${item.account_number || ''})`,
        };
    });
});

// Ensure tableData is always an array
const tableData = computed(() => {
    const data = props.remittances.data;

    if (Array.isArray(data)) {
        return data;
    }

    if (data && typeof data === 'object' && Array.isArray(data.data)) {
        return data.data;
    }

    return [];
});

// Selected banks for display
const selectedSourceBank = computed(() => {
    if (!form.source_bank_id) return null;
    return bankActivities.value.find((bank) => bank.id === form.source_bank_id);
});

const selectedDestinationBank = computed(() => {
    if (!form.destination_bank_id) return null;
    return bankActivities.value.find(
        (bank) => bank.id === form.destination_bank_id,
    );
});

// Computed property for auto-generated narration
const autoNarration = computed(() => {
    if (!selectedSourceBank.value || !selectedDestinationBank.value) {
        return '';
    }

    const source = selectedSourceBank.value;
    const destination = selectedDestinationBank.value;

    return `Transfer from ${source.bank_name} (${source.account_number}) - ${source.title} [${source.tag}] to ${destination.bank_name} (${destination.account_number}) - ${destination.title} [${destination.tag}] for various payments.`;
});

// Check if accounts are the same
const accountsAreSame = computed(() => {
    return (
        form.source_bank_id &&
        form.destination_bank_id &&
        form.source_bank_id === form.destination_bank_id
    );
});

// Check if status is editable - for form fields
const isEditable = (status) => {
    // console.log(usePage().props.auth.userRoles);
    if (usePage().props.auth.userRoles.includes('Admin') || usePage().props.auth.userRoles.includes('admin')) {
        return true;
    }
    if (!status) return true; // New records are editable
    const normalizedStatus = status.toLowerCase().trim();
    return normalizedStatus === 'draft' || normalizedStatus === 'saved';
};

// Method to update auto-narration
const updateAutoNarration = () => {
    setTimeout(() => {
        if (selectedSourceBank.value && selectedDestinationBank.value) {
            const source = selectedSourceBank.value;
            const destination = selectedDestinationBank.value;
            const autoText = `Transfer from ${source.bank_name} (${source.account_number}) - ${source.title} [${source.tag}] to ${destination.bank_name} (${destination.account_number}) - ${destination.title} [${destination.tag}] for various payments.`;

            if (!form.narration || form.narration.includes('Transfer from')) {
                form.narration = autoText;
            }
        }
    }, 100);
};

// Update narration when source bank changes
const onSourceBankChange = () => {
    if (selectedSourceBank.value) {
        form.source_bank = selectedSourceBank.value.bank_name;
    } else {
        form.source_bank = '';
    }
    setTimeout(() => {
        updateAutoNarration();
    }, 50);
};

// Update narration when destination bank changes
const onDestinationBankChange = () => {
    if (selectedDestinationBank.value) {
        form.destination_bank = selectedDestinationBank.value.bank_name;
    } else {
        form.destination_bank = '';
    }
    setTimeout(() => {
        updateAutoNarration();
    }, 50);
};

// Handle auto-fill narration button click
const handleAutoFillNarration = () => {
    if (selectedSourceBank.value && selectedDestinationBank.value) {
        form.narration = autoNarration.value;
    }
};

// Search functions
const performSearch = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    searchTimeout.value = setTimeout(() => {
        router.get(
            '/remittances',
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

// Open new remittance dialog
const openNew = () => {
    form.reset();
    isEdit.value = false;
    form.status = 'Draft';
    form.amount = 0;
    form.transfer_date = new Date();
    form.source_bank_id = null;
    form.destination_bank_id = null;
    form.source_bank = '';
    form.destination_bank = '';
    form.narration = '';
    remittanceDialog.value = true;
};

// View remittance
// const viewRemittance = (id) => {
//     router.get(route('remittances.show', id));
// };
const viewRemittance = (id) => {
    const remittance = tableData.value.find((item) => item.id === id);
    if (remittance) {
        router.get(route('remittances.print', id));
    }
};

const printRemittance = (id) => {
    const printUrl = `/remittances/${id}/print`;
    window.open(printUrl, '_blank');
};

// Edit remittance - with permission check
const editRemittance = (remittance) => {
    if (!canEditRemittance(remittance)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Edit',
            detail: `Remittance ${remittance.receipt_number} is "${remittance.status}" and cannot be edited. Only Draft, Saved, Sent Back, Returned, or Declined remittances can be edited.`,
            life: 5000,
        });
        return;
    }

    isEdit.value = true;
    console.log('Editing remittance:', remittance);

    form.reset();

    form.id = remittance.id;
    form.receipt_number = remittance.receipt_number || '';
    form.transfer_date = remittance.transfer_date
        ? new Date(remittance.transfer_date)
        : new Date();

    if (remittance.source_bank_details?.id) {
        form.source_bank_id = remittance.source_bank_details.id;
        form.source_bank = remittance.source_bank_details.bank_name;
    } else {
        const sourceBank = bankActivities.value.find(
            (bank) => bank.bank_name === remittance.source_bank,
        );
        form.source_bank_id = sourceBank?.id || null;
        form.source_bank = remittance.source_bank || '';
    }

    if (remittance.destination_bank_details?.id) {
        form.destination_bank_id = remittance.destination_bank_details.id;
        form.destination_bank = remittance.destination_bank_details.bank_name;
    } else {
        const destBank = bankActivities.value.find(
            (bank) => bank.bank_name === remittance.destination_bank,
        );
        form.destination_bank_id = destBank?.id || null;
        form.destination_bank = remittance.destination_bank || '';
    }

    form.amount = remittance.amount || 0;
    form.narration = remittance.narration || '';
    form.status = remittance.status || 'Draft';

    remittanceDialog.value = true;

    setTimeout(() => {
        if (
            (!form.narration || form.narration.includes('Transfer from')) &&
            form.source_bank_id &&
            form.destination_bank_id
        ) {
            updateAutoNarration();
        }
    }, 200);
};

// Delete remittance - with permission check
const deleteRemittance = (id) => {
    const remittance = tableData.value.find((item) => item.id === id);

    if (!remittance) return;

    if (!canDeleteRemittance(remittance)) {
        toast.add({
            severity: 'error',
            summary: 'Cannot Delete',
            detail: `Remittance ${remittance.receipt_number} is "${remittance.status}" and cannot be deleted. Only Draft or Saved remittances can be deleted.`,
            life: 5000,
        });
        return;
    }

    if (
        confirm(
            'Are you sure you want to delete this remittance? This action cannot be undone.',
        )
    ) {
        router.delete(route('remittances.destroy', id), {
            preserveState: true,
            onSuccess: () => {
                toast.add({
                    severity: 'info',
                    summary: 'Deleted',
                    detail: 'Remittance deleted successfully',
                    life: 3000,
                });
            },
            onError: () => {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to delete remittance',
                    life: 3000,
                });
            },
        });
    }
};

// Save remittance
// const saveRemittance = (status) => {
//     // Set the status based on the button clicked
//     form.status = status;

//     if (selectedSourceBank.value && !form.source_bank) {
//         form.source_bank = selectedSourceBank.value.bank_name;
//     }
//     if (selectedDestinationBank.value && !form.destination_bank) {
//         form.destination_bank = selectedDestinationBank.value.bank_name;
//     }

//     // For CREATE mode: Validate accounts are different
//     if (!isEdit.value) {
//         if (
//             form.source_bank_id &&
//             form.destination_bank_id &&
//             form.source_bank_id === form.destination_bank_id
//         ) {
//             toast.add({
//                 severity: 'error',
//                 summary: 'Validation Error',
//                 detail: 'Source account and destination account cannot be the same.',
//                 life: 5000,
//             });
//             return;
//         }
//     }

//     if (form.transfer_date) {
//         const date = new Date(form.transfer_date);
//         form.transfer_date = date.toISOString().split('T')[0];
//     }

//     console.log('Form data for ' + (isEdit.value ? 'EDIT' : 'CREATE') + ':', {
//         ...form.data(),
//         status: form.status,
//     });

//     if (isEdit.value) {
//         form.put(`/remittances/${form.id}`, {
//             preserveScroll: true,
//             preserveState: true,
//             onSuccess: () => {
//                 remittanceDialog.value = false;
//                 toast.add({
//                     severity: 'success',
//                     summary: 'Updated',
//                     detail: `Remittance ${form.status === 'Pending Approval' ? 'submitted for approval' : 'saved as draft'} successfully`,
//                     life: 3000,
//                 });
//                 router.visit('/remittances', {
//                     preserveScroll: true,
//                     preserveState: true,
//                     replace: true,
//                 });
//             },
//             onError: (errors) => {
//                 console.error('Form errors:', errors);
//                 toast.add({
//                     severity: 'error',
//                     summary: 'Validation Error',
//                     detail: errors.error || 'Please check the form for errors',
//                     life: 5000,
//                 });
//             },
//         });
//     } else {
//         form.post('/remittances', {
//             preserveScroll: true,
//             preserveState: true,
//             onSuccess: () => {
//                 remittanceDialog.value = false;
//                 toast.add({
//                     severity: 'success',
//                     summary: 'Created',
//                     detail: `Remittance ${form.status === 'Pending Approval' ? 'submitted for approval' : 'saved as draft'} successfully`,
//                     life: 3000,
//                 });
//                 router.visit('/remittances', {
//                     preserveScroll: true,
//                     preserveState: true,
//                     replace: true,
//                 });
//             },
//             onError: (errors) => {
//                 console.error('Form errors:', errors);
//                 toast.add({
//                     severity: 'error',
//                     summary: 'Validation Error',
//                     detail: errors.error || 'Please check the form for errors',
//                     life: 5000,
//                 });
//             },
//         });
//     }
// };

// Save remittance
const saveRemittance = (actionType) => {
    // Set the status based on the action
    let newStatus = 'Draft';
    if (actionType === 'submit') {
        newStatus = 'Submitted'; // Changed from 'Pending Approval' to match voucher logic
    }

    form.status = newStatus;

    if (selectedSourceBank.value && !form.source_bank) {
        form.source_bank = selectedSourceBank.value.bank_name;
    }
    if (selectedDestinationBank.value && !form.destination_bank) {
        form.destination_bank = selectedDestinationBank.value.bank_name;
    }

    // For CREATE mode: Validate accounts are different
    if (!isEdit.value) {
        if (
            form.source_bank_id &&
            form.destination_bank_id &&
            form.source_bank_id === form.destination_bank_id
        ) {
            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: 'Source account and destination account cannot be the same.',
                life: 5000,
            });
            return;
        }
    }

    if (form.transfer_date) {
        const date = new Date(form.transfer_date);
        form.transfer_date = date.toISOString().split('T')[0];
    }

    console.log('Form data for ' + (isEdit.value ? 'EDIT' : 'CREATE') + ':', {
        ...form.data(),
        status: form.status,
    });

    if (isEdit.value) {
        form.put(`/remittances/${form.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                remittanceDialog.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Updated',
                    detail: `Remittance ${form.status === 'Submitted' ? 'submitted for approval' : 'saved as draft'} successfully`,
                    life: 3000,
                });
                router.visit('/remittances', {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                });
            },
            onError: (errors) => {
                console.error('Form errors:', errors);
                toast.add({
                    severity: 'error',
                    summary: 'Validation Error',
                    detail: errors.error || 'Please check the form for errors',
                    life: 5000,
                });
            },
        });
    } else {
        form.post('/remittances', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                remittanceDialog.value = false;
                toast.add({
                    severity: 'success',
                    summary: 'Created',
                    detail: `Remittance ${form.status === 'Submitted' ? 'submitted for approval' : 'saved as draft'} successfully`,
                    life: 3000,
                });
                router.visit('/remittances', {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                });
            },
            onError: (errors) => {
                console.error('Form errors:', errors);
                toast.add({
                    severity: 'error',
                    summary: 'Validation Error',
                    detail: errors.error || 'Please check the form for errors',
                    life: 5000,
                });
            },
        });
    }
};

// Number to words function
const convertNumberToWords = (amount) => {
    if (isNaN(amount) || amount === 0) return 'Zero Naira';

    const units = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
    ];
    const teens = [
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];
    const tens = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

    const convertHundreds = (num) => {
        let result = '';
        if (num >= 100) {
            result += units[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }
        if (num >= 20) {
            result += tens[Math.floor(num / 10)] + ' ';
            num %= 10;
        } else if (num >= 10) {
            result += teens[num - 10] + ' ';
            num = 0;
        }
        if (num > 0) {
            result += units[num] + ' ';
        }
        return result.trim();
    };

    let words = '';
    let nairaAmount = Math.floor(amount);
    let koboAmount = Math.round((amount - nairaAmount) * 100);

    if (nairaAmount >= 1000000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000000)) + ' Billion ';
        nairaAmount %= 1000000000;
    }
    if (nairaAmount >= 1000000) {
        words +=
            convertHundreds(Math.floor(nairaAmount / 1000000)) + ' Million ';
        nairaAmount %= 1000000;
    }
    if (nairaAmount >= 1000) {
        words += convertHundreds(Math.floor(nairaAmount / 1000)) + ' Thousand ';
        nairaAmount %= 1000;
    }
    if (nairaAmount > 0) {
        words += convertHundreds(nairaAmount) + ' ';
    }

    words = words.trim();
    words += words ? ' Naira' : 'Zero Naira';

    if (koboAmount > 0) {
        words += ' and ';
        if (koboAmount >= 20) {
            words += tens[Math.floor(koboAmount / 10)] + ' ';
            koboAmount %= 10;
        } else if (koboAmount >= 10) {
            words += teens[koboAmount - 10] + ' ';
            koboAmount = 0;
        }
        if (koboAmount > 0) {
            words += units[koboAmount] + ' ';
        }
        words += 'Kobo';
    }

    return words.trim() + ' Only';
};

// Computed property for amount in words
const amountInWords = computed(() => {
    return convertNumberToWords(form.amount || 0);
});

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

    router.get(route('remittances.index'), queryParams, {
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

    router.get(route('remittances.index'), queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Format currency
const formatCurrency = (value) => {
    if (!value && value !== 0) return 'â‚¦0.00';
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);
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
        case 'pending approval':
        case 'pending':
        case 'submitted':
            return 'info';
        case 'approved':
        case 'completed':
        case 'paid':
            return 'success';
        case 'declined':
        case 'rejected':
        case 'failed':
        case 'returned':
        case 'sent back':
            return 'danger';
        default:
            return 'secondary';
    }
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
});

// Add to your props
const filterOptions = ref(props.filter_options || {});

// Add filters object
const filters = ref({
    receipt_number: props.filters?.receipt_number || '',
    date_range: props.filters?.date_range || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
    amount_range: props.filters?.amount_range || '',
    min_amount: props.filters?.min_amount || null,
    max_amount: props.filters?.max_amount || null,
    treasury: props.filters?.treasury || '',
    status: props.filters?.status || '',
    sort_by: props.filters?.sort_by || '',
    sort_order: props.filters?.sort_order || 'desc',
});

// Computed properties
const showCustomDateRange = computed(
    () => filters.value.date_range === 'custom',
);
const showCustomAmountRange = computed(
    () => filters.value.amount_range === 'custom',
);

const hasActiveFilters = computed(() => {
    return Object.entries(filters.value).some(([key, value]) => {
        if (key === 'sort_order') return false;
        return value && value !== '' && value !== null;
    });
});

const activeFilters = computed(() => {
    const active = {};
    Object.entries(filters.value).forEach(([key, value]) => {
        if (value && value !== '' && value !== null && key !== 'sort_order') {
            active[key] = value;
        }
    });
    return active;
});

// Methods
const getFilterLabel = (key, value) => {
    const labels = {
        receipt_number: `Receipt: ${value}`,
        date_range: `Date: ${filterOptions.value.date_options?.[value] || value}`,
        amount_range: `Amount: ${filterOptions.value.amount_presets?.[value] || value}`,
        treasury: `Treasury: ${value}`,
        status: `Status: ${filterOptions.value.statuses?.[value] || value}`,
        sort_by: `Sort: ${filters.value.sort_by} (${filters.value.sort_order})`,
        date_from: `From: ${formatDate(value)}`,
        date_to: `To: ${formatDate(value)}`,
        min_amount: `Min: ${formatCurrency(value)}`,
        max_amount: `Max: ${formatCurrency(value)}`,
    };
    return labels[key] || `${key}: ${value}`;
};

const removeFilter = (key) => {
    if (key === 'date_from' || key === 'date_to') {
        filters.value.date_range = '';
        filters.value.date_from = '';
        filters.value.date_to = '';
    } else if (key === 'min_amount' || key === 'max_amount') {
        filters.value.amount_range = '';
        filters.value.min_amount = null;
        filters.value.max_amount = null;
    } else {
        filters.value[key] = '';
    }
    applyFilters();
};

const clearAllFilters = () => {
    filters.value = {
        receipt_number: '',
        date_range: '',
        date_from: '',
        date_to: '',
        amount_range: '',
        min_amount: null,
        max_amount: null,
        treasury: '',
        status: '',
        sort_by: '',
        sort_order: 'desc',
    };
    applyFilters();
};

const onDateRangeChange = () => {
    if (filters.value.date_range !== 'custom') {
        filters.value.date_from = '';
        filters.value.date_to = '';
    }
    applyFilters();
};

const onAmountRangeChange = () => {
    if (filters.value.amount_range !== 'custom') {
        filters.value.min_amount = null;
        filters.value.max_amount = null;
    }
    applyFilters();
};

const toggleSortOrder = () => {
    filters.value.sort_order =
        filters.value.sort_order === 'asc' ? 'desc' : 'asc';
    applyFilters();
};

const applyFilters = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    searchTimeout.value = setTimeout(() => {
        const filterParams = { ...filters.value };
        Object.keys(filterParams).forEach((key) => {
            if (!filterParams[key] && filterParams[key] !== 0) {
                delete filterParams[key];
            }
        });
        filterParams.page = 1;
        router.get('/remittances', filterParams, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 500);
};

const clearSearch = () => {
    searchQuery.value = '';
    clearAllFilters();
};
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
</style>
