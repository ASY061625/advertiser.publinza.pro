/**
 * The Publinza design system.
 *
 * Import components from `@shared/ui`, never from a file path inside it, so the
 * internal layout stays free to change.
 *
 * QuantBar is exported here for completeness but is a catalog-only component —
 * see its doc comment before reaching for it.
 */

// Foundations
export { Button, type ButtonProps, type ButtonSize, type ButtonVariant } from './Button';
export { IconButton, type IconButtonProps } from './IconButton';
export { Badge, STATUS_KEYS, type StatusKey } from './Badge';
export { Avatar, type AvatarProps } from './Avatar';
export { Card, type CardProps } from './Card';
export { StatCard, type StatCardProps } from './StatCard';
export { QuantBar, type QuantBarProps } from './QuantBar';
export { ProgressBar, type ProgressBarProps } from './ProgressBar';
export { Skeleton, SkeletonText, type SkeletonProps } from './Skeleton';
export { Alert, type AlertProps, type AlertTone } from './Alert';
export { EmptyState, type EmptyStateProps } from './EmptyState';
export { Breadcrumb, type Crumb } from './Breadcrumb';
export { Tabs, type TabItem, type TabsProps } from './Tabs';

// Form controls
export { Field, type FieldProps } from './Field';
export { Input, type InputProps } from './Input';
export { NumberInput, type NumberInputProps } from './NumberInput';
export { Textarea, type TextareaProps } from './Textarea';
export { Select, type SelectOption, type SelectProps } from './Select';
export { MultiSelect, type MultiSelectOption, type MultiSelectProps } from './MultiSelect';
export { Combobox, type ComboboxOption, type ComboboxProps } from './Combobox';
export { RangeSlider, type RangeSliderProps } from './RangeSlider';
export { Checkbox, type CheckboxProps } from './Checkbox';
export { RadioGroup, type RadioGroupProps, type RadioOption } from './Radio';
export { Switch, type SwitchProps } from './Switch';
export { Calendar, formatIso, parseIso, toIso, type IsoDate } from './Calendar';
export { DatePicker, type DatePickerProps } from './DatePicker';
export { DateRangePicker, type DateRange, type DateRangePickerProps } from './DateRangePicker';

// Overlays
export { Modal, type ModalProps } from './Modal';
export { Drawer, type DrawerProps } from './Drawer';
export { Dropdown, type DropdownItem, type DropdownProps } from './Dropdown';
export { Tooltip, type TooltipProps } from './Tooltip';
export { Toast, ToastProvider, useToast, type ToastMessage, type ToastTone } from './Toast';

// Data display
export { Table, type Column, type SortDirection, type SortState, type TableProps } from './Table';
export { DataGridToolbar, type DataGridToolbarProps } from './DataGridToolbar';
export { Pagination, type PaginationProps } from './Pagination';

export * from './icons';
