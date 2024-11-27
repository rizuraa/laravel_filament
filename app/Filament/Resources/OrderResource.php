<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;


class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->options(Customer::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                DatePicker::make('order_date')
                    ->label('Tanggal Order')
                    ->default(now())
                    ->required(),
                
                TextInput::make('total')
                    ->label('Total')                    
                    ->numeric()                    
                    ->reactive(),

                Repeater::make('items')
                    ->label('Order Items')
                    ->relationship('items')
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::all()->pluck('name', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $product = Product::find($state);
                                if ($product) {
                                    $set('price', $product->price);
                                    $quantity = $get('quantity') ?? 1;
                                    $set('subtotal', $product->price * $quantity);
                                }
                                static::updateTotal($get, $set);
                            }),

                        TextInput::make('price')
                            ->label('Harga')
                            ->disabled()
                            ->numeric(),

                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $price = $get('price') ?? 0;
                                $set('subtotal', $price * $state);
                                static::updateTotal($get, $set);
                            }),

                        TextInput::make('subtotal')
                            ->label('Subtotal')                            
                            ->numeric(),
                    ])
                    ->createItemButtonLabel('Tambah Item')
                    ->defaultItems(1)
                    ->disableLabel()
                    ->columns(4)
                    ->reactive()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        static::updateTotal($get, $set);
                    })
                    ->statePath('items')
                    ->afterStateHydrated(function (callable $get, callable $set) {
                        static::updateTotal($get, $set); // Ensure total is updated after hydration
                    }),
            ]);
               
    }

    protected static function updateTotal(callable $get, callable $set)
    {
        $total = collect($get('items') ?? [])
            ->sum(fn ($item) => $item['subtotal'] ?? 0);
        $set('total', $total);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer'),
                Tables\Columns\TextColumn::make('order_date')->label('Tanggal Order')->date(),
                Tables\Columns\TextColumn::make('total')->money('IDR'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
