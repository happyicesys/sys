import{g,T as h,h as N,c as S,a as i,w as u,p as O,o,b as e,f as a,l as m,t as n,k as b,F as w,n as k,u as _,e as j,d as C}from"./app.f3f8417a.js";import{_ as x}from"./Button.560137c1.js";import{_ as P}from"./Modal.8e7d5670.js";import{_ as F}from"./MultiSelect.b5db08de.js";import{r as D}from"./PlusCircleIcon.4287f4e9.js";import{r as J}from"./ArrowUturnLeftIcon.6bcecaaa.js";import{r as T}from"./CheckCircleIcon.383f8e2b.js";import{r as A}from"./BackspaceIcon.7ada22c8.js";import"./open-closed.aa6761c3.js";import"./disposables.798a6dc4.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1faf467b.js";const E={class:"flex flex-col md:flex-row space-x-2"},R={key:0,class:"text-gray-600"},z={key:1},L={key:2,class:"text-gray-600"},U={class:"overflow-hidden bg-white shadow sm:rounded-lg"},q={class:"border-t border-gray-200 px-4 py-5 sm:p-0"},G={class:"sm:divide-y sm:divide-gray-200"},H={class:"py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6"},I=e("dt",{class:"text-sm font-medium text-gray-500"}," Name ",-1),K={class:"mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"},Q={class:"py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6"},W=e("dt",{class:"text-sm font-medium text-gray-500"}," Remarks ",-1),X={class:"mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"},Y={class:"py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6"},Z=e("dt",{class:"text-sm font-medium text-gray-500"},"Channel - Product",-1),ee={class:"mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0"},te={role:"list",class:"divide-y divide-gray-200 rounded-md border border-gray-200"},se={class:"flex items-center justify-between py-3 pl-3 pr-4 text-sm"},oe={class:"flex w-0 flex-1 space-x-2 items-center"},ae={class:"text-blue-700 text-md pr-2"},de={key:0},ne=["onSubmit"],le={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},ie={key:0,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},re=e("div",{class:"relative"},[e("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[e("div",{class:"w-full border-t border-gray-300"})]),e("div",{class:"relative flex justify-center"},[e("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Vending Machine Binding ")])],-1),ce=[re],me={key:1,class:"sm:col-span-5"},pe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine ",-1),ue={key:0,class:"text-sm text-red-600"},_e={key:2,class:"sm:col-span-1"},xe=e("span",null," Add ",-1),ve={key:3,class:"sm:col-span-6 flex flex-col mt-3"},ge={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},he={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},fe={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},ye={class:"min-w-full divide-y divide-gray-300"},be=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend ID "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend Name "),e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),we={class:"bg-white"},ke={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ce={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Ve={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left"},Be={key:0},$e=e("br",null,null,-1),Me={key:1},Ne={class:"whitespace-nowrap py-4 text-sm text-center"},Se={key:0},Oe=e("td",{colspan:"5",class:"whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center"}," No Records Found ",-1),je=[Oe],Pe={class:"sm:col-span-6"},Fe={class:"flex space-x-1 mt-5 justify-end"},De=e("span",null," Back ",-1),Je=e("span",null," Save ",-1),Qe={__name:"VendForm",props:{productMapping:Object,type:String,showModal:Boolean,unbindedVends:Object},emits:["modalClose"],setup(v,{emit:V}){const c=v,s=g(h(f())),p=g([]),r=g([]);N(()=>{s.value=c.productMapping?h(c.productMapping):h(f()),p.value=c.unbindedVends.data,r.value=JSON.parse(JSON.stringify(c.productMapping.vends))});function f(){return{id:"",name:"",remarks:"",vend_id:""}}function B(){s.value.clearErrors(),c.type==="update"&&s.value.transform(l=>({...l,productMappingVends:r.value})).post("/product-mappings/"+s.value.id+"/update/vends",{onSuccess:()=>{V("modalClose")},preserveState:!0,replace:!0})}function $(){r.value.indexOf(s.value.vend_id)<0&&(r.value.push(s.value.vend_id),r.value.sort((l,d)=>l.code-d.code),p.value.splice(p.value.indexOf(s.value.vend_id),1),p.value.sort((l,d)=>l.code-d.code))}function M(l){r.value.splice(r.value.indexOf(l),1),p.value.push(l),p.value.sort((d,t)=>d.code-t.code)}return(l,d)=>(o(),S(O,{to:"body"},[i(P,{open:v.showModal,onModalClose:d[3]||(d[3]=t=>l.$emit("modalClose"))},{header:u(()=>[e("div",E,[c.productMapping?(o(),a("span",R," Product Mapping for ")):m("",!0),c.productMapping?(o(),a("span",z,n(c.productMapping.name),1)):(o(),a("span",L," Create New Product Mapping "))])]),default:u(()=>[e("div",U,[e("div",q,[e("dl",G,[e("div",H,[I,e("dd",K,n(s.value.name),1)]),e("div",Q,[W,e("dd",X,n(s.value.remarks),1)]),e("div",Y,[Z,e("dd",ee,[e("ul",te,[(o(!0),a(w,null,b(v.productMapping.productMappingItemsJson,t=>(o(),a("li",se,[e("div",oe,[e("span",ae,n(t.channel_code),1),t.product.code?(o(),a("span",de,n(t.product.code),1)):m("",!0),e("span",null," - "+n(t.product.name),1)])]))),256))])])])])])]),e("form",{onSubmit:j(B,["prevent"]),id:"submit"},[e("div",le,[s.value.id?(o(),a("div",ie,ce)):m("",!0),s.value.id?(o(),a("div",me,[pe,i(F,{modelValue:s.value.vend_id,"onUpdate:modelValue":d[0]||(d[0]=t=>s.value.vend_id=t),options:p.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),s.value.errors.vend_id?(o(),a("div",ue,n(s.value.errors.vend_id),1)):m("",!0)])):m("",!0),s.value.id?(o(),a("div",_e,[i(x,{type:"button",onClick:d[1]||(d[1]=t=>$()),class:k(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[s.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!s.value.vend_id},{default:u(()=>[i(_(D),{class:"w-4 h-4"}),xe]),_:1},8,["class","disabled"])])):m("",!0),s.value.id?(o(),a("div",ve,[e("div",ge,[e("div",he,[e("div",fe,[e("table",ye,[be,e("tbody",we,[(o(!0),a(w,null,b(r.value,(t,y)=>(o(),a("tr",{key:t.id,class:k(y%2===0?void 0:"bg-gray-50")},[e("td",ke,n(y+1),1),e("td",Ce,n(t.code),1),e("td",Ve,[t.latestVendBinding&&t.latestVendBinding.customer?(o(),a("span",Be,[C(n(t.latestVendBinding.customer.code)+" ",1),$e,C(" "+n(t.latestVendBinding.customer.name),1)])):(o(),a("span",Me,n(t.name),1))]),e("td",Ne,[i(x,{class:"bg-red-400 hover:bg-red-500 text-white",onClick:Te=>M(t)},{default:u(()=>[i(_(A),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])],2))),128)),r.value.length?m("",!0):(o(),a("tr",Se,je))])])])])])])):m("",!0)]),e("div",Pe,[e("div",Fe,[i(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:d[2]||(d[2]=t=>l.$emit("modalClose")),form:"submit"},{default:u(()=>[i(_(J),{class:"w-4 h-4"}),De]),_:1}),i(x,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:u(()=>[i(_(T),{class:"w-4 h-4"}),Je]),_:1})])])],40,ne)]),_:1},8,["open"])]))}};export{Qe as default};
