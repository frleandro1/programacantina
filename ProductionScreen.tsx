import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { CheckCircle2, Package, Plus, Clock } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface ProductionEntry {
  id: string;
  product: string;
  quantity: number;
  timestamp: string;
  responsible: string;
}

const PRODUCTS = [
  { id: "1", name: "Pão de Queijo", category: "Salgado" },
  { id: "2", name: "Bolo de Cenoura", category: "Doce" },
  { id: "3", name: "Coxinha", category: "Salgado" },
  { id: "4", name: "Brigadeiro", category: "Doce" },
  { id: "5", name: "Suco Natural", category: "Bebida" },
  { id: "6", name: "Café", category: "Bebida" },
  { id: "7", name: "Sanduíche", category: "Salgado" },
  { id: "8", name: "Salada", category: "Refeição" },
];

export default function ProductionScreen() {
  const [selectedProduct, setSelectedProduct] = useState("");
  const [quantity, setQuantity] = useState("");
  const [responsible, setResponsible] = useState("");
  const [entries, setEntries] = useState<ProductionEntry[]>([
    {
      id: "1",
      product: "Pão de Queijo",
      quantity: 50,
      timestamp: "2024-01-15 08:30",
      responsible: "Maria Silva",
    },
    {
      id: "2",
      product: "Bolo de Cenoura",
      quantity: 30,
      timestamp: "2024-01-15 09:00",
      responsible: "João Santos",
    },
    {
      id: "3",
      product: "Coxinha",
      quantity: 100,
      timestamp: "2024-01-15 09:45",
      responsible: "Maria Silva",
    },
  ]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedProduct || !quantity || !responsible) {
      toast.error("Preencha todos os campos obrigatórios");
      return;
    }

    const quantityNum = parseInt(quantity);
    if (isNaN(quantityNum) || quantityNum <= 0) {
      toast.error("Quantidade deve ser um número positivo");
      return;
    }

    const product = PRODUCTS.find((p) => p.id === selectedProduct);
    if (!product) return;

    const newEntry: ProductionEntry = {
      id: String(entries.length + 1),
      product: product.name,
      quantity: quantityNum,
      timestamp: new Date().toLocaleString("pt-BR"),
      responsible,
    };

    setEntries([newEntry, ...entries]);
    setSelectedProduct("");
    setQuantity("");
    setResponsible("");

    toast.success(`${quantityNum} unidades de ${product.name} registradas com sucesso!`);
  };

  const totalProduced = entries.reduce((sum, entry) => sum + entry.quantity, 0);

  return (
    <div className="space-y-6">
      {/* Hero Image */}
      <div className="relative rounded-xl overflow-hidden h-64 md:h-80">
        <img
          src="/production-dashboard.jpg"
          alt="Tela de Produção"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-cyan-900/70 to-transparent flex items-center">
          <div className="text-white p-6 md:p-8">
            <h2 className="text-3xl md:text-4xl font-bold mb-2">Tela de Produção</h2>
            <p className="text-cyan-100">Registre produtos recém-produzidos</p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Form Section */}
        <div className="lg:col-span-2">
          <Card className="border-cyan-200">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Plus className="w-5 h-5 text-cyan-600" />
                Registrar Produção
              </CardTitle>
              <CardDescription>
                Adicione novos produtos ao estoque
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="space-y-2">
                  <Label htmlFor="product" className="text-gray-700 font-semibold">
                    Produto *
                  </Label>
                  <Select value={selectedProduct} onValueChange={setSelectedProduct}>
                    <SelectTrigger id="product" className="border-cyan-200">
                      <SelectValue placeholder="Selecione um produto" />
                    </SelectTrigger>
                    <SelectContent>
                      {PRODUCTS.map((product) => (
                        <SelectItem key={product.id} value={product.id}>
                          {product.name} ({product.category})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="quantity" className="text-gray-700 font-semibold">
                    Quantidade Produzida *
                  </Label>
                  <Input
                    id="quantity"
                    type="number"
                    min="1"
                    placeholder="Ex: 50"
                    value={quantity}
                    onChange={(e) => setQuantity(e.target.value)}
                    className="border-cyan-200"
                  />
                  <p className="text-xs text-gray-500">Insira a quantidade de unidades produzidas</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="responsible" className="text-gray-700 font-semibold">
                    Responsável pela Produção *
                  </Label>
                  <Input
                    id="responsible"
                    type="text"
                    placeholder="Ex: Maria Silva"
                    value={responsible}
                    onChange={(e) => setResponsible(e.target.value)}
                    className="border-cyan-200"
                  />
                  <p className="text-xs text-gray-500">Nome de quem registrou a produção</p>
                </div>

                <Button
                  type="submit"
                  className="w-full bg-gradient-to-r from-cyan-600 to-green-600 hover:from-cyan-700 hover:to-green-700 text-white font-semibold py-2"
                >
                  <CheckCircle2 className="w-4 h-4 mr-2" />
                  Registrar Produção
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>

        {/* Stats Section */}
        <div className="space-y-4">
          <Card className="bg-gradient-to-br from-cyan-50 to-cyan-100 border-cyan-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-cyan-900">Total Produzido</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-cyan-600">{totalProduced}</div>
              <p className="text-xs text-cyan-700 mt-2">Unidades hoje</p>
            </CardContent>
          </Card>

          <Card className="bg-gradient-to-br from-green-50 to-green-100 border-green-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-green-900">Registros</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-green-600">{entries.length}</div>
              <p className="text-xs text-green-700 mt-2">Produções registradas</p>
            </CardContent>
          </Card>

          <Card className="bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium text-blue-900">Produtos</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-bold text-blue-600">{PRODUCTS.length}</div>
              <p className="text-xs text-blue-700 mt-2">Cadastrados no sistema</p>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* History Section */}
      <Card className="border-cyan-200">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="w-5 h-5 text-cyan-600" />
            Histórico de Produções
          </CardTitle>
          <CardDescription>
            Últimas produções registradas
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="border-cyan-200 hover:bg-cyan-50">
                  <TableHead className="text-gray-700 font-semibold">Produto</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Quantidade</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Data/Hora</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Responsável</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {entries.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="text-center text-gray-500 py-8">
                      <Package className="w-8 h-8 mx-auto mb-2 opacity-50" />
                      <p>Nenhuma produção registrada ainda</p>
                    </TableCell>
                  </TableRow>
                ) : (
                  entries.map((entry) => (
                    <TableRow key={entry.id} className="border-cyan-100 hover:bg-cyan-50">
                      <TableCell className="font-medium text-gray-900">{entry.product}</TableCell>
                      <TableCell className="text-cyan-600 font-semibold">{entry.quantity} un</TableCell>
                      <TableCell className="text-gray-600 text-sm">{entry.timestamp}</TableCell>
                      <TableCell className="text-gray-600">{entry.responsible}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
