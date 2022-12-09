import{i as g,u as p,j as w,o as d,c as V,a as t,w as l,T as S,d as o,g as c,p as k,t as y,f as i,b as x,e as C}from"./app.2279999a.js";import{_ as b}from"./Button.3f9fa165.js";import{_ as u}from"./FormInput.3a76dcdd.js";import{_ as $}from"./Modal.7fdf8150.js";import{_ as B}from"./MultiSelect.913894fa.js";import{r as U}from"./ArrowUturnLeftIcon.f22a68ae.js";import{r as j}from"./CheckCircleIcon.47952971.js";import"./open-closed.d795e59a.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.270ba674.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},O={key:1},E={key:2,class:"text-gray-600"},T=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"col-span-12 sm:col-span-6"},P=i(" Name "),q={class:"col-span-12 sm:col-span-6"},L=i(" Email "),R={class:"col-span-12 sm:col-span-6"},z=i(" Username "),A={class:"col-span-12 sm:col-span-6"},G={class:"col-span-12 sm:col-span-6"},H=o("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Role ",-1),I={class:"sm:col-span-6"},J={class:"flex space-x-1 mt-5 justify-end"},K=o("span",null," Back ",-1),Q=o("span",null," Save ",-1),re={__name:"Form",props:{user:Object,countries:Object,roles:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(n,{emit:f}){const r=n,e=g(p(m())),_=g([]);w(()=>{e.value=r.user?p({...m(),...r.user}):p(m()),_.value=r.roles});function m(){return{name:"",email:"",username:"",password:""}}function h(){e.value.clearErrors(),r.type==="create"&&e.value.post("/users/create",{onSuccess:()=>{f("modalClose")},preserveState:!0,replace:!0}),r.type==="update"&&e.value.post("/users/"+e.value.id+"/update",{onSuccess:()=>{f("modalClose")},preserveState:!0,replace:!0})}return(v,s)=>(d(),V(S,{to:"body"},[t($,{open:n.showModal,onModalClose:s[6]||(s[6]=a=>v.$emit("modalClose"))},{header:l(()=>[o("div",N,[r.user?(d(),c("span",M," Editing ")):k("",!0),r.user?(d(),c("span",O,y(r.user.name),1)):(d(),c("span",E," Create New User "))])]),default:l(()=>[o("form",{onSubmit:C(h,["prevent"]),id:"submit"},[o("div",F,[o("div",D,[t(u,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:l(()=>[P]),_:1},8,["modelValue","error"])]),o("div",q,[t(u,{modelValue:e.value.email,"onUpdate:modelValue":s[1]||(s[1]=a=>e.value.email=a),error:e.value.errors.email},{default:l(()=>[L]),_:1},8,["modelValue","error"])]),o("div",R,[t(u,{modelValue:e.value.username,"onUpdate:modelValue":s[2]||(s[2]=a=>e.value.username=a),error:e.value.errors.username},{default:l(()=>[z]),_:1},8,["modelValue","error"])]),o("div",A,[t(u,{modelValue:e.value.password,"onUpdate:modelValue":s[3]||(s[3]=a=>e.value.password=a),error:e.value.errors.password,placeholderStr:[n.type=="update"?"Leave blank for same password":""],inputType:"password",autocomplete:"new-password"},{default:l(()=>[i(" Password "+y(n.type=="update"?"(Override)":""),1)]),_:1},8,["modelValue","error","placeholderStr"])]),o("div",G,[H,t(B,{modelValue:e.value.role_id,"onUpdate:modelValue":s[4]||(s[4]=a=>e.value.role_id=a),options:_.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),o("div",I,[o("div",J,[t(b,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[5]||(s[5]=a=>v.$emit("modalClose")),form:"submit"},{default:l(()=>[t(x(U),{class:"w-4 h-4"}),K]),_:1}),t(b,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[t(x(j),{class:"w-4 h-4"}),Q]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{re as default};
