import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { AlertCircle, ShoppingCart, Plus, Clock, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface SalesItem {
  productId: string;
  productName: string;
  quantity: number;
  price: number;
}

interface SalesEntry {
  id: string;
  items: SalesItem[];
  total: number;
  timestamp: string;
  attendant: string;
}

const PRODUCTS = [
  { id: "1", name: "Pão de Queijo", price: 3.5, stock: 150 },
  { id: "2", name: "Bolo de Cenoura", price: 5.0, stock: 45 },
  { id: "3", name: "Coxinha", price: 4.0, stock: 200 },
  { id: "4", name: "Brigadeiro", price: 2.5, stock: 120 },
  { id: "5", name: "Suco Natural", price: 4.5, stock: 80 },
  { id: "6", name: "Café", price: 2.0, stock: 300 },
  { id: "7", name: "Sanduíche", price: 8.0, stock: 60 },
  { id: "8", name: "Salada", price: 6.0, stock: 40 },
];

export default function SalesScreen() {
  const [selectedProduct, setSelectedProduct] = useState("");
  const [quantity, setQuantity] = useState("");
  const [attendant, setAttendant] = useState("");
  const [cartItems, setCartItems] = useState<SalesItem[]>([]);
  const [sales, setSales] = useState<SalesEntry[]>([
    {
      id: "1",
      items: [
        { productId: "1", productName: "Pão de Queijo", quantity: 10, price: 3.5 },
        { productId: "5", productName: "Suco Natural", quantity: 5, price: 4.5 },
      ],
      total: 57.5,
      timestamp: "2024-01-15 10:30",
      attendant: "Ana Costa",
    },
    {
      id: "2",
      items: [{ productId: "3", productName: "Coxinha", quantity: 8, price: 4.0 }],
      total: 32.0,
      timestamp: "2024-01-15 11:00",
      attendant: "Pedro Oliveira",
    },
  ]);

  const handleAddToCart = () => {
    if (!selectedProduct || !quantity) {
      toast.error("Selecione um produto e quantidade");
      return;
    }

    const quantityNum = parseInt(quantity);
    if (isNaN(quantityNum) || quantityNum <= 0) {
      toast.error("Quantidade deve ser um número positivo");
      return;
    }

    const product = PRODUCTS.find((p) => p.id === selectedProduct);
    if (!product) return;

    if (quantityNum > product.stock) {
      toast.error(`Estoque insuficiente. Disponível: ${product.stock}`);
      return;
    }

    const existingItem = cartItems.find((item) => item.productId === selectedProduct);
    if (existingItem) {
      if (existingItem.quantity + quantityNum > product.stock) {
        toast.error(`Estoque insuficiente para adicionar mais itens`);
        return;
      }
      setCartItems(
        cartItems.map((item) =>
          item.productId === selectedProduct
            ? { ...item, quantity: item.quantity + quantityNum }
            : item
        )
      );
    } else {
      setCartItems([
        ...cartItems,
        {
          productId: selectedProduct,
          productName: product.name,
          quantity: quantityNum,
          price: product.price,
        },
      ]);
    }

    setSelectedProduct("");
    setQuantity("");
    toast.success(`${product.name} adicionado ao carrinho`);
  };

  const handleRemoveFromCart = (productId: string) => {
    setCartItems(cartItems.filter((item) => item.productId !== productId));
  };

  const handleCompleteSale = () => {
    if (cartItems.length === 0) {
      toast.error("Adicione produtos ao carrinho");
      return;
    }

    if (!attendant) {
      toast.error("Preencha o nome do atendente");
      return;
    }

    const total = cartItems.reduce((sum, item) => sum + item.quantity * item.price, 0);

    const newSale: SalesEntry = {
      id: String(sales.length + 1),
      items: cartItems,
      total,
      timestamp: new Date().toLocaleString("pt-BR"),
      attendant,
    };

    setSales([newSale, ...sales]);
    setCartItems([]);
    setAttendant("");

    const itemCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);
    toast.success(`Venda de ${itemCount} itens registrada com sucesso! Total: R$ ${total.toFixed(2)}`);
  };

  const cartTotal = cartItems.reduce((sum, item) => sum + item.quantity * item.price, 0);
  const totalSold = sales.reduce((sum, sale) => sum + sale.items.reduce((s, item) => s + item.quantity, 0), 0);

  return (
    <div className="space-y-6">
      {/* Hero Image */}
      <div className="relative rounded-xl overflow-hidden h-64 md:h-80">
        <img
          src="/sales-counter.jpg"
          alt="Tela de Vendas"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-green-900/70 to-transparent flex items-center">
          <div className="text-white p-6 md:p-8">
            <h2 className="text-3xl md:text-4xl font-bold mb-2">Tela de Vendas</h2>
            <p className="text-green-100">Registre produtos vendidos com validação de estoque</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form and Cart Section */}
        <div className="lg:col-span-2 space-y-6">
          {/* Add Product Form */}
          <Card className="border-green-200">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Plus className="w-5 h-5 text-green-600" />
                Adicionar Produto
              </CardTitle>
              <CardDescription>
                Selecione um produto e quantidade para adicionar ao carrinho
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="product" className="text-gray-700 font-semibold">
                    Produto
                  </Label>
                  <Select value={selectedProduct} onValueChange={setSelectedProduct}>
                    <SelectTrigger id="product" className="border-green-200">
                      <SelectValue placeholder="Selecione um produto" />
                    </SelectTrigger>
                    <SelectContent>
                      {PRODUCTS.map((product) => (
                        <SelectItem key={product.id} value={product.id}>
                          {product.name} - R$ {product.price.toFixed(2)} ({product.stock} em estoque)
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="quantity" className="text-gray-700 font-semibold">
                    Quantidade
                  </Label>
                  <Input
                    id="quantity"
                    type="number"
                    min="1"
                    placeholder="Ex: 5"
                    value={quantity}
                    onChange={(e) => setQuantity(e.target.value)}
                    className="border-green-200"
                  />
                </div>
              </div>

              <Button
                onClick={handleAddToCart}
                className="w-full bg-green-600 hover:bg-green-700 text-white font-semibold"
              >
                <Plus className="w-4 h-4 mr-2" />
                Adicionar ao Carrinho
              </Button>
            </CardContent>
          </Card>

          {/* Cart Section */}
          <Card className="border-green-200">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShoppingCart className="w-5 h-5 text-green-600" />
                Carrinho de Vendas
              </CardTitle>
              <CardDescription>
                {cartItems.length} produto(s) no carrinho
              </CardDescription>
            </CardHeader>
            <CardContent>
              {cartItems.length === 0 ? (
                <div className="text-center py-8 text-gray-500">
                  <ShoppingCart className="w-12 h-12 mx-auto mb-3 opacity-30" />
                  <p>Carrinho vazio. Adicione produtos para continuar.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow className="border-green-200 hover:bg-green-50">
                          <TableHead className="text-gray-700 font-semibold">Produto</TableHead>
                          <TableHead className="text-gray-700 font-semibold">Qtd</TableHead>
                          <TableHead className="text-gray-700 font-semibold">Preço</TableHead>
                          <TableHead className="text-gray-700 font-semibold">Subtotal</TableHead>
                          <TableHead className="text-gray-700 font-semibold">Ação</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {cartItems.map((item) => (
                          <TableRow key={item.productId} className="border-green-100 hover:bg-green-50">
                            <TableCell className="font-medium text-gray-900">{item.productName}</TableCell>
                            <TableCell className="text-gray-600">{item.quantity}</TableCell>
                            <TableCell className="text-gray-600">R$ {item.price.toFixed(2)}</TableCell>
                            <TableCell className="font-semibold text-green-600">
                              R$ {(item.quantity * item.price).toFixed(2)}
                            </TableCell>
                            <TableCell>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveFromCart(item.productId)}
                                className="text-red-600 hover:text-red-700 hover:bg-red-50"
                              >
                                <Trash2 className="w-4 h-4" />
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>

                  <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex justify-between items-center text-lg font-bold">
                      <span className="text-gray-900">Total:</span>
                      <span className="text-green-600">R$ {cartTotal.toFixed(2)}</span>
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Checkout Section */}
        <div className="space-y-4">
          <Card className="bg-gradient-to-br from-green-50 to-green-100 border-green-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-green-900">Total da Venda</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600">R$ {cartTotal.toFixed(2)}</div>
              <p className="text-xs text-green-700 mt-2">{cartItems.length} item(ns)</p>
            </CardContent>
          </Card>

          <Card className="border-green-200">
            <CardHeader>
              <CardTitle className="text-sm font-medium text-gray-700">Atendente</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <Input
                type="text"
                placeholder="Nome do atendente"
                value={attendant}
                onChange={(e) => setAttendant(e.target.value)}
                className="border-green-200"
              />
              <Button
                onClick={handleCompleteSale}
                disabled={cartItems.length === 0 || !attendant}
                className="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold py-2 disabled:opacity-50"
              >
                <ShoppingCart className="w-4 h-4 mr-2" />
                Finalizar Venda
              </Button>
            </CardContent>
          </Card>

          <Card className="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-blue-900">Total Vendido</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-blue-600">{totalSold}</div>
              <p className="text-xs text-blue-700 mt-2">Unidades hoje</p>
            </CardContent>
          </Card>

          <Card className="bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-orange-900">Vendas</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-orange-600">{sales.length}</div>
              <p className="text-xs text-orange-700 mt-2">Transações registradas</p>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Sales History */}
      <Card className="border-green-200">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="w-5 h-5 text-green-600" />
            Histórico de Vendas
          </CardTitle>
          <CardDescription>
            Últimas vendas registradas
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {sales.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <ShoppingCart className="w-8 h-8 mx-auto mb-2 opacity-50" />
                <p>Nenhuma venda registrada ainda</p>
              </div>
            ) : (
              sales.map((sale) => (
                <div key={sale.id} className="border border-green-200 rounded-lg p-4 hover:bg-green-50 transition">
                  <div className="flex justify-between items-start mb-3">
                    <div>
                      <p className="font-semibold text-gray-900">Venda #{sale.id}</p>
                      <p className="text-sm text-gray-600">{sale.timestamp}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-bold text-green-600">R$ {sale.total.toFixed(2)}</p>
                      <p className="text-xs text-gray-600">{sale.attendant}</p>
                    </div>
                  </div>
                  <div className="space-y-1 text-sm">
                    {sale.items.map((item, idx) => (
                      <div key={idx} className="flex justify-between text-gray-700">
                        <span>{item.productName}</span>
                        <span className="text-gray-600">{item.quantity} un × R$ {item.price.toFixed(2)}</span>
                      </div>
                    ))}
                  </div>
                </div>
              ))
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
